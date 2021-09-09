<?php

//declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\HEXONET\Client as CL;
use CNIC\HEXONET\Response as R;

final class RRPproxyClientTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \CNIC\RRPproxyClient|null $cl
     */
    public static $cl;

    public static function setUpBeforeClass(): void
    {
        //session_start();
        self::$cl = CF::getClient([
            "registrar" => "RRPproxy"
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        self::$cl = null;
        //session_destroy();
    }

    public function testGetPOSTDataSecured(): void
    {
        self::$cl->setCredentials("qmtest", "7VxeB-FpDhv");
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "CheckAuthentication",
            "SUBUSER" => "qmtest",
            "PASSWORD" => "7VxeB-FpDhv"
        ], true);
        self::$cl->setCredentials("", "");
        $this->assertEquals(
            "s_login=qmtest&s_pw=%2A%2A%2A&COMMAND=CheckAuthentication&SUBUSER=qmtest&PASSWORD=%2A%2A%2A",
            $enc
        );
    }

    public function testGetPOSTDataObj(): void
    {
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "ModifyDomain",
            "AUTH" => "gwrgwqg%&\\44t3*"
        ]);
        $this->assertEquals("COMMAND=ModifyDomain&AUTH=gwrgwqg%25%26%5C44t3%2A", $enc);
    }

    public function testGetPOSTDataStr(): void
    {
        $enc = self::$cl->getPOSTData("command=statusaccount");
        $this->assertEquals("COMMAND=statusaccount", $enc);
    }

    public function testGetPOSTDataNull(): void
    {
        $enc = self::$cl->getPOSTData([
            "COMMAND" => "ModifyDomain",
            "AUTH" => null
        ]);
        $this->assertEquals($enc, "COMMAND=ModifyDomain");
    }

    public function testEnableDebugMode(): void
    {
        self::$cl->enableDebugMode();
        $this->assertEquals(1, 1);//suppress warning for risky test
    }

    public function testDisableDebugMode(): void
    {
        self::$cl->disableDebugMode();
        $this->assertEquals(1, 1);//suppress warning for risky test
    }

    public function testGetSession(): void
    {
        $sessid = self::$cl->getSession();
        $this->assertNull($sessid);
    }

    public function testGetSessionIDSet(): void
    {
        $sess = "testsession12345";
        $sessid = self::$cl->setSession($sess)->getSession();
        $this->assertEquals($sessid, $sess);
        self::$cl->setSession("");
    }

    public function testGetURL(): void
    {
        $url = self::$cl->getURL();
        $this->assertEquals($url, self::$cl->settings["env"]["live"]["url"]);
    }

    public function testGetUserAgent(): void
    {
        $ua = "PHP-SDK (" . PHP_OS . "; " . php_uname("m") . "; rv:" . self::$cl->getVersion() . ") php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        $this->assertEquals(self::$cl->getUserAgent(), $ua);
    }

    public function testSetUserAgent(): void
    {
        $pid = "WHMCS";
        $rv = "7.7.0";
        $ua = $pid . " (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $rv . ") php-sdk/" . self::$cl->getVersion() . " php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        $cls = self::$cl->setUserAgent($pid, $rv);
        $this->assertInstanceOf(CL::class, $cls);
        $this->assertEquals(self::$cl->getUserAgent(), $ua);
    }

    public function testSetUserAgentModules(): void
    {
        $pid = "WHMCS";
        $rv = "7.7.0";
        $mods = ["reg/2.6.2", "ssl/7.2.2", "dc/8.2.2"];
        $ua = $pid . " (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $rv . ") " . implode(" ", $mods) . " php-sdk/" . self::$cl->getVersion() . " php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        $cls = self::$cl->setUserAgent($pid, $rv, $mods);
        $this->assertInstanceOf(CL::class, $cls);
        $this->assertEquals(self::$cl->getUserAgent(), $ua);
    }

    public function testSetURL(): void
    {
        $oldurl = self::$cl->getURL();
        $hostname = parse_url($oldurl, PHP_URL_HOST);
        $newurl = str_replace($hostname, "127.0.0.1", $oldurl);

        $url = self::$cl->setURL($newurl)->getURL();
        $this->assertEquals($url, $newurl);
        self::$cl->setURL($oldurl);
    }

    public function testSetOTPSet(): void
    {
        $this->expectException(\Exception::class);
        self::$cl->setOTP("12345678");
    }

    public function testSetOTPReset(): void
    {
        self::$cl->setOTP("");
        $tmp = self::$cl->getPOSTData([
          "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "COMMAND=StatusAccount");
    }

    public function testSetSessionSet(): void
    {
        self::$cl->setSession("12345678");
        $tmp = self::$cl->getPOSTData([
          "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_sessionid=12345678&COMMAND=StatusAccount");
    }

    public function testSetSessionCredentials(): void
    {
        // credentials and otp code have to be unset when session id is set
        self::$cl->setRoleCredentials("myaccountid", "myrole", "mypassword")
                ->setSession("12345678");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_sessionid=12345678&COMMAND=StatusAccount");
    }

    public function testSetSessionReset(): void
    {
        self::$cl->setSession("");
        $tmp = self::$cl->getPOSTData([
          "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "COMMAND=StatusAccount");
    }

    public function testSaveReuseSession(): void
    {
        self::$cl->setSession("12345678")
                ->saveSession($_SESSION);
        $cl2 = CF::getClient([
            "registrar" => "RRPproxy"
        ]);
        $cl2->reuseSession($_SESSION);
        $tmp = $cl2->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_sessionid=12345678&COMMAND=StatusAccount");
        self::$cl->setSession("");
    }

    public function testSetRemoteIPAddressSet(): void
    {
        $this->expectException(\Exception::class);
        self::$cl->setRemoteIPAddress("10.10.10.10");
    }

    public function testSetRemoteIPAddressReset(): void
    {
        self::$cl->setRemoteIPAddress("");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "COMMAND=StatusAccount");
    }

    public function testSetCredentialsSet(): void
    {
        self::$cl->setCredentials("myaccountid", "mypassword");
        $tmp = self::$cl->getPOSTData([
          "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_login=myaccountid&s_pw=mypassword&COMMAND=StatusAccount");
    }

    public function testSetCredentialsReset(): void
    {
        self::$cl->setCredentials("", "");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "COMMAND=StatusAccount");
    }

    public function testSetRoleCredentialsSet(): void
    {
        self::$cl->setRoleCredentials("myaccountid", "myroleid", "mypassword");
        $tmp = self::$cl->getPOSTData([
          "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "s_login=myaccountid%3Amyroleid&s_pw=mypassword&COMMAND=StatusAccount");
    }

    public function testSetRoleCredentialsReset(): void
    {
        self::$cl->setRoleCredentials("", "", "");
        $tmp = self::$cl->getPOSTData([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertEquals($tmp, "COMMAND=StatusAccount");
    }

    public function testLoginCredsOK(): void
    {
        $this->markTestSkipped('RSRTPM-3111');//TODO
        self::$cl->useOTESystem()
                ->setCredentials("qmtest", "7VxeB-FpDhv");
        $r = self::$cl->login();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $rec = $r->getRecord(0);
        $this->assertNotNull($rec);
        $this->assertNotNull($rec->getDataByKey("SESSIONID"));
    }

    /*public function testLoginRoleCredsOK(): void
    {
        self::$cl->setRoleCredentials("qmtest", "testrole", "7VxeB-FpDhv");
        $r = self::$cl->login();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $rec = $r->getRecord(0);
        $this->assertNotNull($rec);
        $this->assertNotNull($rec->getDataByKey("SESSION"));
    }*/

    public function testLoginCredsFAIL(): void
    {
        self::$cl->setCredentials("qmtest", "WRONGPASSWORD");
        $r = self::$cl->login();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isError(), true);
    }

    //TODO -> not covered: login failed; http timeout
    //TODO -> not covered: login succeeded; no session returned

    public function testLoginExtendedCredsOK(): void
    {
        $this->markTestSkipped('RSRTPM-3111');//TODO
        self::$cl->useOTESystem()
                ->setCredentials("qmtest", "7VxeB-FpDhv");
        $r = self::$cl->loginExtended([
            "TIMEOUT" => 60
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $rec = $r->getRecord(0);
        $this->assertNotNull($rec);
        $this->assertNotNull($rec->getDataByKey("SESSION"));
    }

    public function testLogoutOK(): void
    {
        $this->markTestSkipped('RSRTPM-3111');//TODO
        $r = self::$cl->logout();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
    }

    public function testLogoutFAIL(): void
    {
        $r = self::$cl->logout();
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isError(), true);
    }

    public function testRequestFlattenCommand(): void
    {
        self::$cl->setCredentials("qmtest", "7VxeB-FpDhv")
                ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "CheckDomains",
            "DOMAIN" => ["example.com", "example.net"]
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $this->assertEquals($r->getCode(), 200);
        $this->assertEquals($r->getDescription(), "Command completed successfully");
        $cmd = $r->getCommand();
        $keys = array_keys($cmd);
        $this->assertEquals(in_array("DOMAIN0", $keys), true);
        $this->assertEquals(in_array("DOMAIN1", $keys), true);
        $this->assertEquals(in_array("DOMAIN", $keys), false);
        $this->assertEquals($cmd["DOMAIN0"], "example.com");
        $this->assertEquals($cmd["DOMAIN1"], "example.net");
    }

    public function testRequestAUTOIdnConvert(): void
    {
        self::$cl->setCredentials("qmtest", "7VxeB-FpDhv")
                ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "CheckDomains",
            "DOMAIN" => ["example.com", "dömäin.com", "example.net"]
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $this->assertEquals($r->getCode(), 200);
        $this->assertEquals($r->getDescription(), "Command completed successfully");
        $cmd = $r->getCommand();
        $keys = array_keys($cmd);
        // TODO: may run over sub-checkresults to see them not as error returned
        // TODO: check response for converted idn name eventually
        $this->assertEquals(in_array("DOMAIN0", $keys), true);
        $this->assertEquals(in_array("DOMAIN1", $keys), true);
        $this->assertEquals(in_array("DOMAIN2", $keys), true);
        $this->assertEquals(in_array("DOMAIN", $keys), false);
        $this->assertEquals($cmd["DOMAIN0"], "example.com");
        $this->assertEquals($cmd["DOMAIN1"], "dömäin.com");
        $this->assertEquals($cmd["DOMAIN2"], "example.net");
    }

    public function testRequestAUTOIdnConvert2(): void
    {
        $this->markTestSkipped('RSRTPM-3167');//TODO
        self::$cl->setCredentials("qmtest", "7VxeB-FpDhv")
                ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "QueryObjectlogList",
            "OBJECTID" => "dömäin.com",
            "OBJECTCLASS" => "DOMAIN",
            "MINDATE" => date("Y-m-d H:i:s"),
            "LIMIT" => 1
        ]);
        $this->assertInstanceOf(R::class, $r);
        print_r($r->getPlain());
        die();
        $this->assertEquals($r->isSuccess(), true);
        $cmd = $r->getCommand();
        $this->assertEquals($r->getCode(), 200);
        $keys = array_keys($cmd);
        $this->assertEquals(in_array("OBJECTID", $keys), true);
        $this->assertEquals($cmd["OBJECTID"], "dömäin.com");
    }

    public function testRequestAUTOIdnConvert3(): void
    {
        $this->markTestSkipped('RSRTPM-3167');//TODO
        self::$cl->setCredentials("qmtest", "7VxeB-FpDhv")
                ->useOTESystem();
        $r = self::$cl->request([
            "COMMAND" => "QueryObjectlogList",
            "OBJECTID" => "dömäin.com",
            "OBJECTCLASS" => "SSLCERT",
            "MINDATE" => date("Y-m-d H:i:s"),
            "LIMIT" => 1
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), false);
        $cmd = $r->getCommand();
        $this->assertEquals($r->getCode(), 541);
        $keys = array_keys($cmd);
        $this->assertEquals(in_array("OBJECTID", $keys), true);
        $this->assertEquals($cmd["OBJECTID"], "dömäin.com");
    }


    public function testRequestCodeTmpErrorDbg(): void
    {
        self::$cl->enableDebugMode()
                ->setCredentials("qmtest", "7VxeB-FpDhv")
                ->useOTESystem();
        $r = self::$cl->request(["COMMAND" => "StatusAccount"]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $this->assertEquals($r->getCode(), 200);
        $this->assertEquals($r->getDescription(), "Command completed successfully");
        //TODO: this response is a tmp error in node-sdk; "httperror" template
    }

    public function testRequestCodeTmpErrorNoDbg(): void
    {
        self::$cl->disableDebugMode();
        $r = self::$cl->request([ "COMMAND" => "StatusAccount" ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $this->assertEquals($r->getCode(), 200);
        $this->assertEquals($r->getDescription(), "Command completed successfully");
        //TODO: this response is a tmp error in node-sdk; "httperror" template
    }

    public function testRequestNextResponsePageNoLast(): void
    {
        $r = self::$cl->request([
            "COMMAND" => "QueryDomainList",
            "LIMIT" => 2,
            "FIRST" => 0
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $nr = self::$cl->requestNextResponsePage($r);
        $this->assertInstanceOf(R::class, $nr);
        $this->assertEquals($nr->isSuccess(), true);
        $this->assertEquals($r->getRecordsLimitation(), 2);
        $this->assertEquals($nr->getRecordsLimitation(), 2);
        $this->assertEquals($r->getRecordsCount(), 2);
        $this->assertEquals($nr->getRecordsCount(), 2);
        $this->assertEquals($r->getFirstRecordIndex(), 0);
        $this->assertEquals($r->getLastRecordIndex(), 1);
        $this->assertEquals($nr->getFirstRecordIndex(), 2);
        $this->assertEquals($nr->getLastRecordIndex(), 3);
    }

    public function testRequestNextResponsePageLast(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Parameter LAST in use. Please remove it to avoid issues in requestNextPage.");
        $r = self::$cl->request([
            "COMMAND" => "QueryDomainList",
            "LIMIT" => 2,
            "FIRST" => 0,
            "LAST"  => 1
        ]);
        $this->assertInstanceOf(R::class, $r);
        self::$cl->requestNextResponsePage($r);
    }

    public function testRequestNextResponsePageNoFirst(): void
    {
        self::$cl->disableDebugMode();
        $r = self::$cl->request([
            "COMMAND" => "QueryDomainList",
            "LIMIT" => 2
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
        $nr = self::$cl->requestNextResponsePage($r);
        $this->assertInstanceOf(R::class, $nr);
        $this->assertEquals($nr->isSuccess(), true);
        $this->assertEquals($r->getRecordsLimitation(), 2);
        $this->assertEquals($nr->getRecordsLimitation(), 2);
        $this->assertEquals($r->getRecordsCount(), 2);
        $this->assertEquals($nr->getRecordsCount(), 2);
        $this->assertEquals($r->getFirstRecordIndex(), 0);
        $this->assertEquals($r->getLastRecordIndex(), 1);
        $this->assertEquals($nr->getFirstRecordIndex(), 2);
        $this->assertEquals($nr->getLastRecordIndex(), 3);
    }

    public function testRequestAllResponsePagesOK(): void
    {
        $pages = self::$cl->requestAllResponsePages([
            "COMMAND" => "QueryDomainList",
            "FIRST" => 0,
            "LIMIT" => 100
        ]);
        $this->assertGreaterThan(0, count($pages));
        foreach ($pages as &$p) {
            $this->assertInstanceOf(R::class, $p);
            $this->assertEquals($p->isSuccess(), true);
        }
    }

    public function testSetUserView(): void
    {
        $this->markTestSkipped('RSRTPM-3111');//TODO
        self::$cl->setUserView("docutest01");
        $r = self::$cl->request([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
    }

    public function testResetUserView(): void
    {
        self::$cl->setUserView();
        $r = self::$cl->request([
            "COMMAND" => "StatusAccount"
        ]);
        $this->assertInstanceOf(R::class, $r);
        $this->assertEquals($r->isSuccess(), true);
    }

    public function testSetProxy(): void
    {
        self::$cl->setProxy("127.0.0.1");
        $this->assertEquals(self::$cl->getProxy(), "127.0.0.1");
        self::$cl->setProxy("");
    }

    public function testSetReferer(): void
    {
        self::$cl->setReferer("https://www.hexonet.net/");
        $this->assertEquals(self::$cl->getReferer(), "https://www.hexonet.net/");
        self::$cl->setReferer("");
    }

    public function testUseHighPerformanceConnectionSetup(): void
    {
        $oldurl = self::$cl->getURL();
        $hostname = parse_url($oldurl, PHP_URL_HOST);
        $newurl = str_replace($hostname, "127.0.0.1", $oldurl);
        self::$cl->useHighPerformanceConnectionSetup();
        $this->assertEquals(self::$cl->getURL(), $newurl);
    }
}
