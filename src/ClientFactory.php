<?php

namespace CNIC;

class ClientFactory
{
    /**
     * Returns Client Instance by configuration
     * @param array $params configuration settings
     * @param \CNIC\HEXONET\Logger $logger Logger Instance (optional)
     * @return \CNIC\HEXONET\SessionClient
     * @throws \Exception
     */
    public static function getClient($params, $logger = null)
    {
        if (!preg_match("/^HEXONET|RRPproxy$/", $params["registrar"])) {
            throw new \Exception("Registrar `" . $params["registrar"] . "` not supported.");
        }
        $clientClass = "\\CNIC\\" . $params["registrar"] . "\\SessionClient";
        $cl = new $clientClass();
        if (!empty($params["sandbox"])) {
            $cl->useOTESystem();
        }
        if (
            !empty($params["username"])
            && !empty($params["password"])
        ) {
            $cl->setCredentials(
                $params["username"],
                html_entity_decode($params["password"], ENT_QUOTES)
            );
        }
        if (!empty($params["referer"])) {
            $cl->setReferer($params["referer"]);// GLOBALS["CONFIG"]["SystemURL"] TODO
        }
        if (!empty($params["ua"])) {
            $cl->setUserAgent(
                $params["ua"]["name"],
                $params["ua"]["version"],
                $params["ua"]["modules"]
            ); // "WHMCS", $GLOBALS["CONFIG"]["Version"], $modules TODO
        }
        if (!empty($params["logging"])) {
            $cl->enableDebugMode(); // activate logging
        }
        if (!empty($params["proxyserver"])) {
            $cl->setProxy($params["proxyserver"]);
        }
        if (is_null($logger)) {
            $loggerClass = "\\CNIC\\" . $params["registrar"] . "\\Logger";
            $logger = new $loggerClass();
        }
        $cl->setCustomLogger($logger);

        return $cl;
    }

    /**
     * Get the Zone of a TLD
     * @param string $tld TLD
     * @return string
     */
    public static function getZone($tld)
    {
        return strtoupper(str_replace(".", "", $tld));
    }

    /**
     * Get Zones for a list of TLDs
     * @param array $tlds TLDs
     * @return array
     */
    public static function getZones($tlds)
    {
        return array_map(["DomainFactory", "getZone"], $tlds);
    }
}
