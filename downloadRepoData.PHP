<?php
class CI {
    private $serviceMessageID;
    private $BaseMessage;
    private $beginci;
    private $endci;
    private $repo;

    public $Emojis = [
        "auth" => "🔐",
        "backup" => "📦",
        "compile" => "🔨",
        "warn" => "⚠️",
        "fatal" => "⛔️",
        "test" => "📋",
        "delay" => "⏱",
        "success" => "✅",
        "configure" => "⚙️",
        "save" => "💾"
    ];

    public $Statuses;

    public function __construct($rin){
        $this->Statuses = [
            "authentication" => $this->Emojis["auth"]." Authenticating Git...",
            "backup" => $this->Emojis["backup"]." Backing up current environment...",
            "backuperr" => $this->Emojis["fatal"]." Backup failed. Build rejected to be safe.",
            "compile" => $this->Emojis["compile"]." Compiling source code...",
            "fserr" => $this->Emojis["warn"]." Files could not be copied due an error. Fix ASAP.",
            "unitfailed" => $this->Emojis["warn"]." Unit-testing failed. Reverting...",
            "lintfailed" => $this->Emojis["fatal"]." Code linting failed. Build rejected.",
            "patherr" => $this->Emojis["fatal"]." PKI Path is wrong or missing. Verify repository's CI configuration and try again.",
            "unitfailedreverted" => $this->Emojis["fatal"]." Unit-testing failed. Build rejected.",
            "downloading" => $this->Emojis["save"]." Downloading repository...",
            "dwfail" => $this->Emojis["warn"]." Download failed. Build rejected.",
            "wait-tkc" => $this->Emojis["delay"]." Waiting for server authorization ticket...",
            "success" => $this->Emojis["success"]." Build deployed to *production*."
        ];
        $this->BaseMessage = str_replace(urlencode("{TIMESTAMPSTART}"), str_replace("0000", "UTC", date(DATE_RFC2822)), urlencode("*— Intellivoid CI* (*production*) *—*\n\nDeploying *{REPONAME}* to *iv_global:production*...\n*CI started at:* {TIMESTAMPSTART}\n\n{STATUS}\n"));
        if($this->is_hex($rin)){
            $repo = pack("H*", $rin);
            $this->repo = $repo;
            $this->BaseMessage = str_replace(urlencode("{REPONAME}"), $repo, $this->BaseMessage);
            $CMessage = str_replace(urlencode("{STATUS}"), $this->Statuses["wait-tkc"], $this->BaseMessage);
            $Message = file_get_contents("https://api.telegram.org/bot957368446:AAF0YchCo0YHsNvKbKpTwzX51i0bRslXo30/sendMessage?chat_id=-1001329042156&parse_mode=markdown&text=".$CMessage);
            $this->serviceMessageID = json_decode($Message, true)["result"]["message_id"];
            $this->ProcessCI();
        } else {
            die("Invalid CI call.");
        }
    }

    private function is_hex($hex_code) {
        return @preg_match("/^[a-f0-9]{2,}$/i", $hex_code) && !(strlen($hex_code) & 1);
    }


    private function moveOverride($source, $dest){}
    private function isDirEmpty($dir) {
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
          if ($entry != "." && $entry != "..") {
            closedir($handle);
            return false;
          }
        }
        closedir($handle);
        return true;
    }

    private function ProcessCI(){
        $this->updateServiceMessage($this->Statuses["authentication"]);
        sleep(1);
        $this->beginci = microtime(true);
        $this->updateServiceMessage($this->Statuses["downloading"]);
        $routineHash = hash("md5", time().$this->repo);
        
        exec("mkdir /tmp/$routineHash/ && cd /tmp/$routineHash && git clone -b production --single-branch https://4c5f485c0b7d68bb2117559977b7776801fe7edf@github.com/".$this->repo." .");
        if($this->isDirEmpty("/tmp/$routineHash/")) {
            $this->updateServiceMessage($this->Statuses["dwfail"]);
            $this->CleanUp($routineHash);
            die();
        } else {
            // Read path
            $PKIPath = file_get_contents("/tmp/$routineHash/.pki/path");
            if($PKIPath === false) {
                $this->updateServiceMessage($this->Statuses["patherr"]);
                $this->CleanUp($routineHash);
                die();
            }
            $PKIPath = trim($PKIPath);
            $backupHash = hash("md5", time().$PKIPath);
            mkdir("/tmp/$backupHash", 0777);
            $this->updateServiceMessage($this->Statuses["backup"]);
            system("cd /var/intellivoid/$PKIPath && tar czf /tmp/$backupHash/backup.tar.gz . && cd /tmp/$backupHash/ && tar xzf backup.tar.gz && rm -rf backup.tar.gz");
            if(array_diff(scandir("/tmp/$backupHash"), array('.', '..')) !== array_diff(scandir("/var/intellivoid/$PKIPath"), array('.', '..'))){
                $this->updateServiceMessage($this->Statuses["backuperr"]);
                $this->CleanUp($routineHash, $backupHash);
                die();
            }
            $this->updateServiceMessage($this->Statuses["compile"]);
            system("rm -rf /var/intellivoid/$PKIPath");
            mkdir("/var/intellivoid/$PKIPath", 0777);
            system("cp -Rp /tmp/$routineHash/src/. /tmp/$routineHash/. && rm -rf /tmp/$routineHash/src");
            system("cp -Rp /tmp/$routineHash/. /var/intellivoid/$PKIPath");
            if(array_diff(scandir("/tmp/$routineHash"), array('.', '..')) !== array_diff(scandir("/var/intellivoid/$PKIPath"), array('.', '..'))){
                echo var_dump(array_diff(scandir("/tmp/$routineHash"), array('.', '..')));
                echo var_dump(array_diff(scandir("/var/intellivoid/$PKIPath"), array('.', '..')));
                $this->updateServiceMessage($this->Statuses["fserr"]);
                $this->CleanUp($routineHash, $backupHash);
                die();
            } else {
                $this->endci = microtime(true);
                $this->updateServiceMessage($this->Statuses["success"], true);
                $this->CleanUp($routineHash, $backupHash);
                die();
            }
            
        }
    }

    private function CleanUp($routineHash, $backupHash = "") {
        if(empty($backupHash)) {
            system("rm -rf ".escapeshellarg("/tmp/".$routineHash));
        } else {
            system("rm -rf ".escapeshellarg("/tmp/".$routineHash));
            system("rm -rf ".escapeshellarg("/tmp/".$backupHash));
        }
    }
    private function updateServiceMessage($Status, $End = false) {
        if(!$End) {
            $CMessage = str_replace(urlencode("{STATUS}"), $Status, $this->BaseMessage);
            $Message = file_get_contents("https://api.telegram.org/bot957368446:AAF0YchCo0YHsNvKbKpTwzX51i0bRslXo30/editMessageText?chat_id=-1001329042156&message_id=".$this->serviceMessageID."&parse_mode=markdown&text=".$CMessage);
        } else {
            $CMessage = str_replace(urlencode("{STATUS}"), $Status, $this->BaseMessage);
            $CMessage = $CMessage.urlencode("\n*Completed in* ".round(($this->endci - $this->beginci), 2)."*s*");
            $Message = file_get_contents("https://api.telegram.org/bot957368446:AAF0YchCo0YHsNvKbKpTwzX51i0bRslXo30/editMessageText?chat_id=-1001329042156&message_id=".$this->serviceMessageID."&parse_mode=markdown&text=".$CMessage);
        }
    }
}
if(isset($argv[1]))
$CI = new CI($argv[1]);
