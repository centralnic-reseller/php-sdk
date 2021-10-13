<?php

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

use CNIC\HEXONET\ResponseTemplateManager as RTM;
use CNIC\HEXONET\Logger as L;

/**
 * HEXONET API Client
 *
 * @package CNIC\HEXONET
 */

class Domain extends ApiObject
{

    protected $status = null;
    protected $idnLanguage = null;

    public function getDomain()
    {
        return $this->getId();
    }
    public function setDomain($domain)
    {
        $this->setId($domain);
        return $this->setClass("DOMAIN");
    }
    private function check($grp, $premiumEnabled)
    {
        $r = $this->cl->request([
            "COMMAND" => "CheckDomains",
            "PREMIUMCHANNELS" => $premiumEnabled ? "*" : "",
            "DOMAIN" => $grp
        ]);
        // prefill with default data
        $results = array_fill(0, count($grp), [
            "REASON" => "",
            "CLASS" => "",
            "CURRENCY" => "",
            "PRICE" => "",
            "PREMIUMCHANNEL" => "",
            "DOMAINCHECK" => "421 Temporary issue",
            "STATUS" => "UNKNOWN",
            "ISPREMIUM" => false
        ]);
        // add domain, sld, tld
        array_walk($results, function (&$sr, $idx) {
            $sr["DOMAIN"] = $grp[$idx];
            list(
                $sr["SLD"],
                $sr["TLD"]
            ) = explode(".", $grp[$idx], 2);
        });
        // command failed, goodbye
        if (!$r->isSuccess()) {
            return $results;
        }
        // command succeeded
        array_walk($results, function (&$sr, $idx) {
            $rec = $r->getRecord($idx);
            if (is_null($rec)) {
                return;
            }
            // replace row defaults with API Data
            $sr = array_merge($sr, $rec->getData());
            if (empty($sr["DOMAINCHECK"])) {
                $sr["DOMAINCHECK"] = "421 Temporary issue";
            }

            // set availability status
            $code = (int)substr($sr["DOMAINCHECK"], 0, 3);
            $check = substr($sr["DOMAINCHECK"], 3);

            // TLD not supported at HEXONET or check failed
            // e.g. WHMCS does fallback to whois lookup
            if ($code === 549) {
                $sr["STATUS"] = "NOTSUPPORTED";
                return;
            }

            //DOMAIN AVAILABLE
            if ($code === 210) {
                $sr["STATUS"] = "AVAILABLE";
                return;
            }

            if ($code === 211) {
                // $sr::STATUS_REGISTERED already set
                // PREMIUM DOMAINS
                $sr["STATUS"] = "NOTAVAILABLE";
                $sr["ISPREMIUM"] = preg_match("/^PREMIUM_/i", $sr["CLASS"]);
                if (
                    // DOMAIN BLOCKS
                    stripos($sr["REASON"], "block")
                    // RESERVER DOMAIN NAMES
                    || stripos($sr["REASON"], "reserved")
                    // NXD DOMAINS
                    || preg_match("/^collision domain name available \{/i", $check)
                ) {
                    $sr["STATUS"] = "RESERVED";
                    return;
                }

                if (
                    empty($sr["PREMIUMCHANNEL"])
                    || !$sr["ISPREMIUM"]
                    || !preg_match("/^premium domain name available/i", $check)
                ) {
                    return;
                }
                // CASE: PREMIUM / PREMIUM AFTERMARKET
                // available premium domain
                // ----------------------------------------------------
                // TODO premium domain price calculation
                // ----------------------------------------------------
                /*try {
                    $prices = ispapi_GetPremiumPrice($params);
                    $sr["price"] = var_export($prices, true);
                    $sr->setPremiumCostPricing($prices);
                    // TODO why prices empty?
                    if (isset($prices["register"])) {
                        //PREMIUM DOMAIN AVAILABLE
                        $sr->setStatus($sr::STATUS_NOT_REGISTERED);
                    }
                } catch (\Exception $e) {
                    $sr->setPremiumCostPricing([]);
                    $sr->setStatus($sr::STATUS_RESERVED);
                }*/
            }
        });
        return $results;
    }
    public function checkbulk($domains, $premiumEnabled = false)
    {
        $maxSearchGroupSize = $this->cl->settings["maxSearchGroupSize"];
        $query = array_map("mb_strtolower", $domains);
        $results = [];
        foreach (array_chunk($domains, $maxSearchGroupSize) as $grp) {
            $results = array_merge($results, $this->check($grp, $premiumEnabled));
        }
        return array_change_key_case($results, CASE_LOWER);
    }
    public function getSuggestions($searchTerm, $tlds, $premiumEnabled = false, $settings = [])
    {
        // build zone list parameter
        $zones = array_filter($tlds, function ($tld) {
            // IGNORE 3RD LEVEL TLDS - NOT FULLY SUPPORTED BY QueryDomainSuggestionList
            // Suppress .com, .net by configuration
            return (
                !preg_match("/\./", $tld)
                && (
                    empty($settings["suppressWeigthed"])
                    || !preg_match("/^(com|net)$/", $tld)
                )
            );
        });

        // identify maximum no. of results
        $maxDNSuggestions = $this->cl->settings["maxDNSuggestions"];
        $limit = (
            !isset($settings["suggestionsLimit"])
            || $maxDNSuggestions < $settings["suggestionsLimit"]
        ) ? $maxDNSuggestions : $settings["suggestionsLimit"];

        // request domain name suggestions from engine
        $first = 0;
        $command = [
            "COMMAND" => "QueryDomainSuggestionList",
            "KEYWORD" => $searchTerm,
            "ZONE" => $zones,
            "SOURCE" => "ISPAPI-SUGGESTIONS",
            "LIMIT" => $limit
        ];
        $dnsuggestions = [];
        do {
            $command["FIRST"] = $first;
            $r = $this->cl->request($command);
            if (!$r->isSuccess()) {
                break;
            }
            $col = $r->getColumn("DOMAIN");
            if (is_null($col)) {
                break;
            }
            $dnsuggestions = array_merge(
                $dnsuggestions,
                array_values( // re-index
                    array_unique( // filter duplicates
                        array_filter( // remove empty entries
                            $col->getData()
                        )
                    )
                )
            );
            $first += $limit;
        } while (
            (count($results) < $limit)
            && ($r->getRecordsTotalCount() > $first)
        );

        // check the availability, as also taken/reserved/blocked domains could be returned
        return $this->checkbulk($results, $premiumEnabled);
    }

    /**
     * Get the domain's assigned auth code.
     *
     * @param array $params common module parameters
     * @param string $domain puny code domain name
     * @return array
     */
    public function getAuthCode()
    {
        // Expiring Authorization Codes
        // https://confluence.centralnic.com/display/RSR/Expiring+Authcodes
        // pending cases:
        // - RSRBE-3774
        // - RSRBE-3753
        if (preg_match("/\.de$/i", $this->id)) {
            $r = $this->cl->request([
                "COMMAND" => "DENIC_CreateAuthInfo1",
                "DOMAIN" => $this->id
            ]);
        } elseif (preg_match("/\.(eu|be)$/i", $this->id)) {
            $r = $this->cl->request([
                "COMMAND" => "RequestDomainAuthInfo",
                "DOMAIN" => $this->id
            ]);
            // TODO -> PENDING = 1|0
        } else {
            // default case for all other tlds
            $r = $this->status;
        }

        // check response
        if ($r->isSuccess()) {
            if (
                preg_match("/\.(fi|nz)$/i", $this->id)
                && $r->getDataByIndex("TRANSFERLOCK", 0) === "1"
            ) {
                return [
                    "success" => false,
                    "reason" => "LOCKED"
                ];
            }
            $col = $r->getColumn("AUTH");
            if (is_null($col)) {
                return [
                    "success" => false,
                    "reason" => "SENDTOREGISTRANT"
                ];
            }
            if (!strlen($col->getDataByIndex(0))) {
                return [
                    "success" => false,
                    "reason" => "CONTACTSUPPORT"
                ];
            }
            return [
                "success" => true,
                "eppcode" => $col->getDataByIndex(0)
            ];
        }
        return [
            "success" => false,
            "reason" => "ERROR",
            "description" => $r->getDescription(),
            "code" => $r->getCode()
        ];
    }

    private function getIDNLanguage()
    {
        if (is_null($this->idnLanguage)) {
            $r = $this->cl->request([
                "COMMAND" => "CheckIDNLanguage",
                "DOMAIN" => $this->id
            ]);
            if ($r->isSuccess()) {
                $this->idnLanguage = [
                    "success" => true,
                    "language" => strtolower($r->getDataByIndex("LANGUAGE", 0))
                ];
            } else {
                $this->idnLanguage = [
                    "success" => false,
                    "reason" => $r->getDescription(),
                    "code" => $r->getCode()
                ];
            }
        }
        return $this->idnLanguage;
    }

    public function getNameservers($params, $domain)
    {
        if ($this->status->isSuccess()) {
            $col = $this->status->getColumn("NAMESERVER");
            if (!is_null($col)) {
                return $col->getData();
            }
        }
        return [];
    }
}
