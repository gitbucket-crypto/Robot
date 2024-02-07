<?php 
#####################################################################################################
date_default_timezone_set('America/Sao_Paulo');
define('deployURL','https://boe-php.eletromidia.com.br/RMC/Main/Deploy/Files/');

header("Content-Description: File Transfer");
header("Content-Type: application/octet-stream");

define('VERSION','2.00');
define('AUTOR',"#--- PEDRO HENRIQUE SILVA DE DEUS ---# \n Email: pedro.hsdeus@aol.com ".VERSION." \n");

#######################################################################################################
if(PHP_OS=='Linux')
{
    $arg ='develop';
    if($arg=='develop')
    {
        define('bash', 'echo ZWNobyBmYXN0OTAwMiB8IHN1ZG8gLVMgZWNobyBvayA+IC9kZXYvbnVsbCAyPiYx  | base64 -d | bash');
    }
    else
    {
        define('bash','echo ZWNobyAzbDNtMWQxQCB8IHN1ZG8gLVMgZWNobyBvayA+IC9kZXYvbnVsbCAyPiYxCg== | base64 -d  | bash');
    }
}


function init()
{
    if(PHP_OS== "Linux")
    {
        $py = shell_exec(bash.'&& sudo dpkg -s python3 | grep Version'); 
        if(trim($py)=='')
	    {
			shell_exec(bash.' && sudo apt install python3.9-full python3.9-dev python3.9-venv python3-pip -y');
			init();
	    }
        $ver = substr($py, 9, strlen($py));
        $ver = substr($ver, 0, 4);       
        define('python', 'python'. floatval($ver));
    }
    else
    {
        define('python', 'python');
    }
    echo python.PHP_EOL;
}


function killPython()
{
    if(PHP_OS == "Linux")
    {
        @shell_exec("killall -s 9 ".python);
    }
    else
    {
        @exec("taskkill /IM python.exe /F");
    }
}


function checkEthernet()
{
    switch (connection_status())
    {
        case CONNECTION_NORMAL:
            $msg = 'You are connected to internet.';
            echo $msg.PHP_EOL;
            return true;
        break;
        case CONNECTION_ABORTED:
            $msg = 'No Internet connection';
            echo $msg.PHP_EOL;
            return false;
        break;
        case CONNECTION_TIMEOUT:
            $msg = 'Connection time-out';
            echo $msg.PHP_EOL;
            return false;
        break;
        case (CONNECTION_ABORTED & CONNECTION_TIMEOUT):
            $msg = 'No Internet and Connection time-out';
            echo $msg.PHP_EOL;
            return false;
        break;
        default:
            $msg = 'Undefined state';
            echo $msg.PHP_EOL;
            return false;
        break;
    }
}

#######################################################################################################


init();

#################################################################################################

function _main()
{    
	if(checkEthernet() ==true)
	{	
        if(file_exists('init.json')==false)
        {
           if(file_exists('prepare.php')==false)
           {
                $file = deployURL.'preparephp';
                $fp = fopen( getcwd().DIRECTORY_SEPARATOR.'prepare.php','w');
                fwrite($fp,  file_get_contents($file));
                fclose($fp);
           }

           if(PHP_OS=='Linux')
           {
                var_dump(shell_exec('php prepare.php '.bash));
           }
           else 
           {
                shell_exec('php prepare.php '.bash);
           }
        }
        
		deployFiles();
	    firstRun();  
	}
	else
	{
        echo 'offline trying again in 2 minutes'.PHP_EOL;
        sleep(120);
        _main();
	}
    echo '----------------------------------------'."\n";
    echo AUTOR.PHP_EOL;
    echo VERSION.PHP_EOL;
    echo '----------------------------------------'."\n";
}
#################################################################################################

_main();


function firstRun()
{
    $exist =  file_exists( getcwd().DIRECTORY_SEPARATOR.'init.json') ? true:false;
    if($exist==false)
	{
        try
        {   
            killPython();
            sleep(2);                    
            killPython();    
            setMacAddr();
		    setArtifactNumber();
            $lswh = ListDevices();

            registerArtifactHw(getArtifactNumber(), $lswh ,  getMacAddr());
            getTeamviewer();

            $deploy =  @file_exists( getcwd().DIRECTORY_SEPARATOR.'deploy.json') ? true:false;
            if($deploy)
            {
                echo 'send log';
                $log = serialize(file_get_contents( getcwd().DIRECTORY_SEPARATOR.'deploy.json'));

                notify(getArtifactNumber() , $log);
    
                $fp = fopen( getcwd().DIRECTORY_SEPARATOR. 'init.json' ,'w+');
                fwrite($fp, '"'.getMacAddr().'--'.getArtifactNumber().'"');
                fclose($fp);
                chmod( getcwd().DIRECTORY_SEPARATOR. 'init.json', 0777);
                reloadCronjob();
                if(PHP_OS=='Linux')
                {   
                    atualizarAbreSH();
                }
               
                sleep(1);
                @postRun();
            }

        }
        catch(\Exception $e)
        {
            echo 'firstRun()'.PHP_EOL;
            echo $e->getMessage().PHP_EOL;
        }
    }
    if($exist==true)
    {
        @postRun();
    }
    echo VERSION.PHP_EOL;
    logger(VERSION);
    sleep(1);
    exit;
}

//####################################################################################################

function postRun()
{   
    try
    {
        nuceport();
        //----Python Files Update-----------------
        checkforUpdate();
        //--PHP Update-----------------
        checkAutoUpdate();
        //-----------------------------------------------    
        $ret = getCommand(getArtifactNumber());
        if($ret!=null)
        {
            $toschedule = json_decode($ret, true);
            schedule($toschedule);
        }
        runCronjob();
    }
    catch(Exception $e)
    {
        echo 'postRun()'.PHP_EOL;
        logger($e->getMessage());
    }
}

//####################################################################################################

function checkforUpdate()
{
    try
    {
        $whois=getArtifactNumber();
        $whois = str_replace('"','',$whois);

        $result = file_get_contents('https://boe-php.eletromidia.com.br/RMC/Main/Deploy/Files/update.php?whois='.$whois);

        $update = json_decode($result, true);

        if($update['status']=='403')
        {
            echo 'nothing to update'."\r\n";
            logger('nothing to update');
        }
        if($update['status']=='200' |
        $update['status']==200)
        {
            echo 'update files'."\r\n";
            logger('updating files');
            killPython();
            sleep(1);
            killPython();
            sleep(1);
            killPython();
            unlink('deploy.json');
            updateFiles();
        }
    }
    catch(Exception $e)
    {
        logger($e->getMessage());
        checkforUpdate();
    }

}


####################################################################################################
function deployFiles()
{
    $exist = file_exists( getcwd().DIRECTORY_SEPARATOR.'deploy.json')? true: false;
    if($exist==false)
    {
        $deploy='';
        if(getFile('soc.py')==true)
        {
            $deploy.='soc_py - deployed ';
        }
        else $deploy.='soc_py - undeployed ';

        if(getFile('report.py')==true)
        {
            $deploy.='report_py - deployed ';
        }
        else  $deploy.='report_py - undeployed "';


        if(getFile('modem.py')==true)
        {
            $deploy.='modem_py - deployed ';
        }
        else  $deploy.='modem_py - undeployed "';

        $fp = @fopen( getcwd().DIRECTORY_SEPARATOR.'deploy.json' ,'w+');

        fwrite($fp, $deploy);
        fclose($fp);

        chmod( getcwd().DIRECTORY_SEPARATOR.'deploy.json', 0777);
        sleep(1);
    }
}

function getFile($filename)
{
    echo $filename."\n";
    if(file_exists( getcwd().DIRECTORY_SEPARATOR.$filename))
    {
        unlink( getcwd().DIRECTORY_SEPARATOR.$filename);
    }

    $file = deployURL. $filename;

    $fp = fopen( getcwd().DIRECTORY_SEPARATOR.$filename,'w');
    fwrite($fp,  file_get_contents($file));
    fclose($fp);
    chmod( getcwd().DIRECTORY_SEPARATOR.$filename, 0777);
    
    if(file_exists( getcwd().DIRECTORY_SEPARATOR.$filename)==true)
    {
        return true;
    }
    else return false;
}

function updateFiles()
{
    logger('updating a file');
    if(file_exists('deploy.json')==false)
    {
        $deploy='';
        if(getFile('soc.py')==true)
        {
            $deploy.='soc_py - deployed ';
        }
        else $deploy.='soc_py - undeployed ';

        if(getFile('report.py')==true)
        {
            $deploy.='report_py - deployed ';
        }
        else  $deploy.='report_py - undeployed "';

        if(getFile('modem.py')==true)
        {
            $deploy.='modem_py - deployed ';
        }
        else  $deploy.='modem_py - undeployed "';


        $fp = fopen('deploy.json' ,'w+');
        fwrite($fp, $deploy);
        fclose($fp);
        chmod('deploy.json', 0777);
        $_SERVER['Status']='log';;
        if(file_exists('deploy.json')==true )
        {
            echo 'send log';
            $log = serialize(file_get_contents('deploy.json'));
            notify(getArtifactNumber() , $log);
        }
    }
}

//####################################################################################################

function setMacAddr()
{
    if(file_exists(getcwd(). DIRECTORY_SEPARATOR.'mac.json')==false)
    {
        killPython();
		$out =  shell_exec(python.' report.py');
		$json =  json_decode($out, true);

        $fp = fopen(getcwd(). DIRECTORY_SEPARATOR.'mac.json' ,'w+');
        fwrite($fp, '"'. $json["mac"].'"');
        fclose($fp);
        chmod(getcwd(). DIRECTORY_SEPARATOR.'mac.json', 0777);
    }
    else  echo 'mac.json file generated'.PHP_EOL;
}

function getMacAddr()
{
	return ltrim(file_get_contents(getcwd(). DIRECTORY_SEPARATOR.'mac.json'), ' ');
}

function setArtifactNumber()
{
	if(!file_exists(getcwd(). DIRECTORY_SEPARATOR.'artifact.json'))
    {
		$return = handshake(getMacAddr());
		$res = (json_decode($return, true));
        if(empty($res['msg']['artifact']))
        {
            sleep(1); setArtifactNumber(); return false;
        }
		$uid = $res['msg']['artifact'];
		$fp = fopen(getcwd(). DIRECTORY_SEPARATOR.'artifact.json' ,'w+');
		fwrite($fp, '"'.$uid.'"');
		fclose($fp);
		chmod(getcwd(). DIRECTORY_SEPARATOR.'artifact.json', 0777);
	}
	else  echo 'artifact.json file generated'.PHP_EOL;
}

function getArtifactNumber()
{
	return ltrim(file_get_contents(getcwd(). DIRECTORY_SEPARATOR .'artifact.json'), ' ');
}

//####################################################################################################


function ListDevices()
{
    if(PHP_OS=='Linux')
    {
        $lswh = shell_exec('sudo lshw -json'); 
        return $lswh;
    }
    else
    {
        return shell_exec('pnputil /enum-devices /connected');
    }
}

####################################################################################################

function getTeamviewer()
{
    global $conf;
    if(PHP_OS=='Linux')
    {
        $conf =  shell_exec(bash.'&& sudo cat /etc/teamviewer/global.conf');
        $global =  explode(' ',$conf);
    }
    else
    {
        $cmd ="reg query HKEY_LOCAL_MACHINE\SOFTWARE\Teamviewer";
        $conf =  shell_exec($cmd);
        $global =  explode(' ',$conf);
    }
         
    $postdata = http_build_query(
        array(
            'csrf' => md5(time()),
            'artifact' => getArtifactNumber(),
            'clientID'=> $conf
        )
    );

    $opts = array('http' =>
        array(
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postdata
        )
    );
     $context  = stream_context_create($opts);

    $result = file_get_contents('https://boe-php.eletromidia.com.br/RMC/Hardware/teamviewer.php', false, $context);

    echo $result.PHP_EOL;

    logger(strval($result));
 
}



//#####################################################################################################
function getTemp()
{
    if(PHP_OS == 'Linux')
    {
        return  shell_exec('sensors');
    }
    else
    {
        return shell_exec('wmic OS get TotalVisibleMemorySize /Value & wmic OS get FreePhysicalMemory /Value');
    }
}


function getMemory()
{
    if(PHP_OS == 'Linux')
    {
     
       return  shell_exec('free -m');
    }
    else
    {
        return shell_exec('wmic ComputerSystem get TotalPhysicalMemory   & wmic OS get FreePhysicalMemory ');
    }
}

function getCPU()
{
    if(PHP_OS == 'Linux')
    {        
        return shell_exec('iostat -c 1 1');
    }
    else
    {
        return shell_exec('wmic cpu get loadpercentage /format:Value');
    }
}

function getHDD()
{
    if(PHP_OS == 'Linux')
    {
        return shell_exec('df -ht ext4');
    }
    else
    {
        return shell_exec('wmic /node:"%COMPUTERNAME%" LogicalDisk Where DriveType="3" Get DeviceID,FreeSpace, Size|find /I "c:"');
    }
}

function forceReboot()
{
    if(PHP_OS == 'Linux')
    {
        ///etc/sudoers
        //%www-data ALL=NOPASSWD: /sbin/reboot
        shell_exec('sudo /sbin/reboot');
    }
    else
    {
        exec('shutdown /r /t 0');
    }
}

function nuceport()
{
    if(PHP_OS=='Linux')
    {
        $whois= ltrim(getArtifactNumber(),' ');
        $whois = str_replace('"','',$whois);

        $temp = getTemp();
        $temp = json_encode($temp, true);

        $mem = (string)getMemory();
        $memo=  substr($mem, 88, strlen($mem));
        $memo = ltrim($memo);
        $memo = str_ireplace(' ',';',$memo);
        $memo = explode(';;;;;;;', $memo);
        $total = $memo[0];
        $usada = ltrim($memo[1],';');
        $livre = floatval($memo[0])- floatval($usada);

        $memory= ' total:'.$total.'  usada:'.$usada.'  livre:'.$livre;
        $memory = trim($memory);
        print($memory."\r\n");

        $cpu = getCPU();
        $cpu = ltrim($cpu);
        $cpus = substr($cpu, 130, strlen($cpu));
        $cpus = ltrim($cpus);
        $cpus = explode(" ",$cpus) ;

        $load = $cpus[0];
        if(isset($cpus[19]))
        {
        $iddle = $cpus[19];
        }
        else $iddle = $cpus[15];
        $cpuUsage = ' load:'.$load.'  iddle:'.$iddle;
        $cpuUsage = trim(ltrim($cpuUsage,' '));
        print($cpuUsage."\r\n");

        $disk = getHDD();
        $disk = ltrim($disk);
        $hdd = substr($disk,  intval(strlen($disk)-7) , intval(strlen($disk)) );
        $htt = 'hd usage'.str_replace('/','',$hdd);
        $htd = ltrim($htt, '  ');
        $htd = trim($htd);
        print($htd."\r\n");

        if(floatval($load)>floatval('95.00'))
        {
            sendData($memory, $cpuUsage, $htd,'alto uso de cpu reboot imediato',$whois, $temp);
            sleep(1);
            forceReboot();
            sleep(1);
            forceReboot();
        }

        if(floatval($usada/$total)>floatval(0.80))
        {
            sendData($memory, $cpuUsage, $htd,'alto uso de memoria reboot imediato',$whois, $temp);
            sleep(1);
            forceReboot();
            sleep(1);
            forceReboot();
        }
        sendData($memory, $cpuUsage, $htd,'normal',$whois, $temp);
    }
    else
    {
        $whois= ltrim(getArtifactNumber(),' ');
        $whois = str_replace('"','',$whois);

        $cpu = getCPU();
        $cpu = trim($cpu);
        $cpu = ltrim($cpu,'  ');
        $cpu = explode('=',$cpu,PHP_INT_MAX);
        $iddle= floatval(100) - floatval($cpu[1]);
        $load = $cpu[1];
        $cpuUsage = 'load:'.$cpu[1].'  iddle:'.$iddle;
        print_r($cpuUsage);
        echo PHP_EOL;

        $disk = getHDD();
        $disk = trim($disk);
        $disk = explode(' ',$disk,PHP_INT_MAX);
        $free = floatval($disk[8]);
        $total = floatval($disk[10]);
        $used = $total - $free;
        $percent = (($used*100)/$total);
        $htt = 'hd usage '.round($percent).'%';
        print_r($htt);
        echo PHP_EOL;

        $mem = trim(strval(getMemory()));
        $temp = substr($mem, strlen('TotalPhysicalMemory'), strlen($mem));
        $totMem =trim( substr($temp, 0, 14));

        $freeMem =trim( substr($temp,47,strlen($temp)));

        if(strlen($freeMem)==7)
        {
            $freeMem.='000';
        }

        $usedMemo = strval(intval($totMem) - intval($freeMem));

        $memory ='total: '.formatBytes(intval($totMem)).' usada: '.formatBytes(intval($usedMemo)).' livre: '.formatBytes(intval($freeMem));
        echo $memory.PHP_EOL;

        if(floatval($load)>floatval('95.00'))
        {
            sendData($memory, $cpuUsage, $htt,'alto uso de cpu reboot imediato',$whois, $temp);
            sleep(1);
            forceReboot();
            sleep(1);
            forceReboot();
        }

        if(floatval($usedMemo/$totMem)>floatval(0.80))
        {
            sendData($memory, $cpuUsage, $htt,'alto uso de memoria reboot imediato',$whois, $temp);
            sleep(1);
            forceReboot();
            sleep(1);
            forceReboot();
        }
        sendData($memory, $cpuUsage, $htt,'normal',$whois, $temp);
    }

}


function utf8_str_split(string $input, int $splitLength = 1)
{
    $re = \sprintf('/\\G.{1,%d}+/us', $splitLength);
    \preg_match_all($re, $input, $m);
    return $m[0];
}

function formatBytes($bytes, $precision = 2) { 
    $i = floor(log($bytes) / log(1024));
    $sizes = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');

    return sprintf('%.02F', $bytes / pow(1024, $i)) * 1 . ' ' . $sizes[$i];
} 

//#####################################################################################################
function checkAutoUpdate()
{
   try
   {
        $version = file_get_contents('https://boe-php.eletromidia.com.br/RMC/Update/PHP/version.txt');
        $version = substr($version,0,5);
        $version=  str_ireplace('v','',$version).PHP_EOL;
        if (floatval($version)> floatval(VERSION))
        {
            echo 'php self update '. $version.PHP_EOL;
            logger('self update php');
            @selfUpdate();
        }
        else 
        {
            logger('Versão mais antiga no servidor ');
            echo 'Versão mais antiga no servidor '.PHP_EOL; 
            return false;
        }
   }
   catch(Exception $e)
   {
        logger($e->getMesssage());
   }
}

function selfUpdate()
{
    $updatedCode = file_get_contents('https://boe-php.eletromidia.com.br/RMC/Update/PHP/robotphp');
    if(empty($updatedCode))
    {
        echo 'no code on server'.PHP_EOL;
        return false;
    }
    // Overwrite the current class code with the updated code
    file_put_contents(__FILE__, $updatedCode);
    require_once __FILE__;
}

//#####################################################################################################

function createCronjob()
{
    logger('creating crontab.bkp');
    if(PHP_OS=='Linux')
    {
        if(file_exists(getcwd().DIRECTORY_SEPARATOR.'crontab.bkp'))
        {
            unlink(getcwd().DIRECTORY_SEPARATOR.'crontab.bkp');
        }
        $crontab= "# Edit this file to introduce tasks to be run by cron.
        #
        # Each task to run has to be defined through a single line
        # indicating with different fields when the task will be run
        # and what command to run for the task
        #
        # To define the time you can provide concrete values for
        # minute (m), hour (h), day of month (dom), month (mon),
        # and day of week (dow) or use '*' in these fields (for 'any').#
        # Notice that tasks will be started based on the cron's system
        # daemon's notion of time and timezones.
        #
        # Output of the crontab jobs (including errors) is sent through
        # email to the user the crontab file belongs to (unless redirected).
        #
        # For example, you can run a backup of all your user accounts
        # at 5 a.m every week with:
        # 0 5 * * 1 tar -zcf /var/backups/home.tgz /home/
        #
        # For more information see the manual pages of crontab(5) and cron(8)
        #
        # m h  dom mon dow   command
        */6 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_grade.php >/dev/null 2>&1
        */7 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_slots.php >/dev/null 2>&1
        */8 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_midias.php?cron=true >/dev/null 2>&1
        */2 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_conteudos.php >/dev/null 2>&1
        */3 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_imagens.php?cron=true >/dev/null 2>&1
        */5 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_news.php?cron=true >/dev/null 2>&1
        */2 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_informativos_new.php?cron=true >/dev/null 2>&1
        */5 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_cambio.php >/dev/null 2>&1
        */5 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_criptomoedas.php >/dev/null 2>&1
        */5 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_indices.php >/dev/null 2>&1
        */5 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_transito.php >/dev/null 2>&1
        */10 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_config.php >/dev/null 2>&1
        */10 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_status_vias.php >/dev/null 2>&1
        */10 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_previsao_tempo.php >/dev/null 2>&1
        */10 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_dados_predio.php >/dev/null 2>&1
        */10 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_mural.php >/dev/null 2>&1
        */30 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_barra.php >/dev/null 2>&1
        */30 */12 * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/trash_colector.php >/dev/null 2>&1
        */10 * * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/envia_audit.php >/dev/null 2>&1
        */15 * * * * /var/www/html/elemidia_v4/fscommand/execscreen.sh >/dev/null 2>&1
        * */6 * * * wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/update_sistema.php >/dev/null 2>&1
        */10 * * * * /var/www/html/elemidia_v4/fscommand/rport.sh >/dev/null 2>&1
        */10 * * * * cd /var/www/html/elemidia_v4/fscommand/ && /usr/bin/python3 soc.py
        * */1 * * * cd /var/www/html/elemidia_v4/fscommand/ && /usr/bin/python3 modem.py
        */10 * * * * cd /var/www/html/elemidia_v4/fscommand/ && /usr/bin/php -f robot.php
        ";
        $crontab  = trim($crontab);
        $file = fopen(getcwd().DIRECTORY_SEPARATOR."crontab.bkp", "w+") or die("Unable to open file!");
        fwrite($file, $crontab);
        shell_exec(bash);    
    
    }
    else
    {
        //https://armantutorial.wordpress.com/2022/09/08/how-to-create-scheduled-tasks-with-command-prompt-on-windows-10/
        //schtasks /create /tn "DailyBackup" /tr "\"%SystemRoot%\System32\cmd.exe\" /c \"%scriptPath%\"" /sc daily /st 17:00
        //Scheduling Tasks using the command line is a matter of invoking â€œschtasks.exeâ€ with the appropriate parameters. In its simplest form, 
        
        //to create a scheduled task that would run every 5 minutes invoking the hypothetical batch file above could be done by issuing:

        //C:\Users\demouser>schtasks.exe /Create /SC minute /MO "5" /TN "Interspire Cron" /TR "D:\bin\cron.cmd
            
        //    The details of the syntax are well documented and can be consulted for more complex scenarios.
        $batch  ='
            @echo off
            cd "C:/appserv/www/elemidia_v4/fscommand/"
            start php.exe deploy.php
            start php deploy.php
            start python.exe soc.py
            start python.exe modem.py
            start python soc.py
            start python modem.py
        ';

        $batch = trim($batch);

        $file = fopen(getcwd().DIRECTORY_SEPARATOR."cron.bat", "w+");
        fwrite($file, $batch);
        shell_exec(bash);    

        try
        {
            shell_exec('schtasks.exe /Create /SC minute /MO  "10" /TN "cron" /TR "c:/appserv/www/elemidia_v4/fscommand/cron.bat"');
            #shell_exec('schtasks.exe /Create /SC minute /MO  "10" /TN "cron" /TR "c:/appserv/www/elemidia_v4/fscommand/pycron.bat"');

        }
        catch(Exception $e) 
        {
            logger($e->getMessage());
        }

    }
}

function reloadCronjob()
{
   echo 'reseting crontab.bkp'.PHP_EOL;
   logger('reseting crontab.bkp');
   createCronjob();
   atualizarAbreSH();
}

//#########################################--ATUALIZA ABRE.SH--##########################################


function atualizarAbreSH()
{
    if(file_exists('/var/www/html/elemidia/v4/abre.sh'))
    {
        unlink('/var/www/html/elemidia/v4/abre.sh');        
    }
    if(file_exists(dirname(__DIR__,1).DIRECTORY_SEPARATOR.'abre.sh'))
	{
		unlink(dirname(__DIR__,1).DIRECTORY_SEPARATOR.'abre.sh');
	}
    $abre ='#!/bin/bash
    CLIENT_DIR=/var/www/html/elemidia_v4
    CLIENT_SWF="elemidia.swf"
    CLIENT_TITLE="adobe flash"
    CLIENT_BIN="flashplayer"
    CLIENT_NOVO_BIN="elemidia"
    CLIENT_NOVO_TITLE="eletromidiaplayer"
    
    # Variaveis de Controle
    ERROS_FULLSCREEN=0
    
    # Libera o www-data para ter acesso ao X
    xhost +SI:localuser:www-data
    
    cd $CLIENT_DIR
    
    # Extrai a versao nova do sistema
    #unzip -o elemidia_v4.bin
    
    # ForÃ§a escrita dos dados em cache no disco
    sync
    
    # Da permissao nas pastas
    sudo chmod 777 $CLIENT_DIR -R
    
    # Atualiza o crontab
    crontab fscommand/crontab.bkp
    
    # Verifica se deve rodar o player novo
    playerNovo=$(cat cache/dados_predio.xml | grep -Po "(?<=<player_novo>).*(?=</player_novo>)")
    if [[ "$playerNovo" == "1" ]]; then
        CLIENT_BIN=$CLIENT_NOVO_BIN
        CLIENT_TITLE=$CLIENT_NOVO_TITLE
    fi
    
    # Copia o Settings.Sol do Flash
    cp fscommand/settings.sol.bkp ~/.macromedia/Flash_Player/macromedia.com/support/flashplayer/sys/settings.sol &
    
    # abre o CheckLink e garante que fique somente 1 rodando
    kill $(ps aux | grep checkLink.sh | awk "{print $2}") > /dev/null 2>&1
    bash /var/www/html/elemidia_v4/fscommand/checkLink.sh &
    
    # Abre o Socket
    bash /var/www/html/elemidia_v4/fscommand/abreSocket.sh &
    
    # Abre o Init.sh
    bash /var/www/html/elemidia_v4/fscommand/init.sh &
    
    # Abre o Resizer
    kill $(ps aux | grep resize_linux | awk "{print $2}") > /dev/null 2>&1
    cd fscommand
    php resize_linux.php &
    cd $CLIENT_DIR 
    
    # Loop com verificacoes de resolucao e fullscreen
    while true
    do
    if [ ! `pgrep $CLIENT_BIN` ] ; then
    
        if [[ "$playerNovo" == "1" ]]; then
                cd player
            ./$CLIENT_BIN --no-sandbox &
            cd ..
        else 
            ./$CLIENT_BIN $CLIENT_SWF &
            echo "client not running... openning..."
            wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/watchdog.php >/dev/null 2>&1
            wget --spider --timeout=1 --tries=1 http://localhost/elemidia_v4/util/download_dados_predio.php >/dev/null 2>&1
        fi
    fi
    sleep 15
    virtual_connected=$(xrandr | grep "VIRTUAL1 connected" | wc -l)
    
    if [[ $virtual_connected != 1 ]]; then
    
        resize=$(cat system.xml | grep -Po "(?<=<ativar>).*(?=</ativar>)")
        fscommand/exectop.sh &
        windowpid=$(xdotool search "$CLIENT_TITLE" 2> /dev/null)
        
                
        if [[ "$resize" != "1" ]]; then	
            if [ ! -z "$windowpid" ]; then
                    resolucaoClient=$(xdotool getwindowgeometry $windowpid  | awk "/Geometry/{print $2}")
                    resolucaoTela=$(xdpyinfo | awk "/dimensions/{print $2}")
                if [[ $resolucaoClient != $resolucaoTela ]]; then
                    ERROS_FULLSCREEN=$((ERROS_FULLSCREEN+1))
                    if [[ $ERROS_FULLSCREEN -gt 3 ]]; then
                        echo "client not in full screen... closing..."
                        killall $CLIENT_BIN > /dev/null 2>&1
                        unset windowpid
                        unset resolucaoClient
                        unset resolucaoTela
                        ERROS_FULLSCREEN=0
                    fi
                else
                    ERROS_FULLSCREEN=0
                fi
            fi
        fi
    fi
    
    done
    ';
    $abre  = trim($abre);
    $file = fopen(getcwd().DIRECTORY_SEPARATOR.'abre.sh', "w+") or print("Unable to open file abre sh!"); echo PHP_EOL;
    fwrite($file, $abre);
    rename('abre.sh', dirname(__DIR__,1).'/abre.sh');
}

##########################################--END-ATUALIZA ABRE.SH--#######################################


function getCommand($whois)
{
    try
    {
        $whois = str_replace('"','',$whois);
        $result = file_get_contents('https://boe-php.eletromidia.com.br/RMC/Command/command.php?csrf='.md5(time()).'&whois='.$whois);
        return $result;
    }
    catch(Exception $e)
    {
        logger($e->getMessage());
        getCommand($whois);
    }
}

function runCronjob()
{
    if(file_exists(getcwd().DIRECTORY_SEPARATOR.'cron.json')==false |
       @filesize(getcwd().DIRECTORY_SEPARATOR .'cron.json')== 0  )
    {
         logger('nothing in cron job'); return false;
    }

     logger('Jobs to execute '. file_get_contents(getcwd().DIRECTORY_SEPARATOR.'cron.json'));

    $to = file_get_contents(getcwd().DIRECTORY_SEPARATOR.'cron.json');
    $deploy = explode('@', $to);
    $deploy = array_values($deploy);

    for($i=0 ; $i<sizeof($deploy) ; $i++)
    {
        sleep(1);
        if(substr_count($deploy[$i],"|")>=1)
        {
            $dep = explode('|', $deploy[$i]);
            $hour = trim(ltrim($dep[0],' '));
            @$command = trim(trim($dep[1],' '));
            if($hour==strval(date('H:i')))
            {
              logger("executing {$command}");
              $log = _execute($command);
              sleep(1);
              commandReport($log);
              echo $command;
            }
        }
        else
        {
           echo $deploy[$i];
           $log=run($deploy[$i]);
           sleep(1);
           commandReport($log);
        }
    }
}



//#############################################################################################################



function schedule($schedule)
{
    global $command;

    var_dump($schedule);

    if($schedule['data']==false | $schedule['data']=='false')
    {
        print('Nothing to schedule'."\r\n");
        logger('Nothing in schedule');
        return false;
    }
    if($schedule['data']!=false && $schedule['data']!='false')
    {
        if(substr_count($schedule["data"],"|")>=1)
        {
            echo "timerized command "."\r\n";
            logger("timerized command");


            $tos = explode("|",$schedule["data"]);

            $time = $tos[0];
            $command = $tos[1];
            $command = ltrim($command,' ');


            if(file_exists('cron.json') && filesize('cron.json')>0)
            {
                $cronjson = fopen('cron.json', "r") or die("Unable to open file!");
                $cron =  fread($cronjson, filesize('cron.json'));
                fclose($cronjson);
                unlink('cron.json');
            }
            else $cron = null;

            $LDREnable= "0xFF 0x55 0x04 0x21 0x01 0x01 0x01 0x7c";

            switch(trim($command))
            {
                case 'env': case 'reenv':
                    echo 'Re-instaland  ENV'.PHP_EOL;
                    if(PHP_OS=='Linux')
                    {
                            var_dump(shell_exec('php prepare.php '.bash));
                    }
                    else 
                    {
                            shell_exec('php prepare.php '.bash);
                    }
                    logger('Re-instaland  ENV');
                break;
                case 'reset':
                    echo 'reseting'.PHP_EOL;
                    reloadCronjob();
                    logger($command);
                    return false;
                break;
                case 'backlight_on':
                    $command ='ff 5504 66 00 00 ff bd';
                break;
                case 'backlight_off':
                    $command ='ff 55 04 66 00 00 00 be';
                break;
                case 'brilho_min':
                    $command = 'FF 55 04 66 01 02 56 17';
                break;
                case 'brilho_med':
                    $command = 'ff 55 04 66 01 02 28 e9';
                break;
                case 'brilho_max':
                    $command = 'ff 55 04 66 01 02 64 25';
                break;
                case 'display_on':
                    $command = 'FF 55 04 84 01 01 00 de';
                break;
                case 'display_off':
                    echo $command;
                    $command = 'FF 55 04 83 01 01 00 dd';
                break;
            }
            $command = trim($command);
            $command = str_replace(' ', '', $command);

            if (date("H:00:00", strtotime($time )) == date("H:i:00", strtotime($time )))
            {
                $date =  str_replace(":00", "", $time);
            }
            else
            {
                $minute =  str_replace("00:", "", $time);
                $date = date('H:i:s', strtotime("now +{$minute} minutes"));
            }

            if(!is_null($command))
            {
                if(PHP_OS== "Linux")
                {
                    killPython();
                    sleep(2);                    
                    killPython();
                    @run(" /usr/bin/python3 ".getcwd().DIRECTORY_SEPARATOR."soc.py {$LDREnable}");
                    killPython();
                    sleep(1);
                    killPython();
                    $line = "{$date} |  /usr/bin/python3 ".getcwd().DIRECTORY_SEPARATOR."soc.py $command";
                }
                else
                {
                    killPython();
                    sleep(2);                    
                    killPython();
                    @run(" python3 ".getcwd().DIRECTORY_SEPARATOR."soc.py {$LDREnable}");
                    killPython();
                    sleep(2);                    
                    killPython();
                    $line = "{$date} |  python3 ".getcwd().DIRECTORY_SEPARATOR."soc.py $command";
                }
                $cron .="\n".$line.' @';
                echo $cron."\n";

                $file = fopen("cron.json", "w+");
                fwrite($file, $cron."\n");
                fclose($file);
            }
            unset($command);
        }
        else 
        {
            //if(substr_count($schedule["data"],"|")==0 && $schedule["data"] !='false')
            $command = trim($schedule["data"]);
            $command = str_replace(' ', '', $command);


            logger('schedule de brilho');
            echo 'Schedule de brilho'."\r\n";

            $LDRdisable=ltrim('0xFF 0x55 0x04 0x21 0x01 0x01 0x00 0x7b',' ');

            if(file_exists('cron.json') && filesize('cron.json')>0)
            {
                echo 'cron.json'.PHP_EOL;

                $cronjson = fopen('cron.json', "r") or die("Unable to open file!");
                $cron =  fread($cronjson,filesize('cron.json'));
                fclose($cronjson);
                unlink('cron.json');
            }
            else $cron = '';

            if(PHP_OS== "Linux")
            {
                killPython();
                sleep(2);                    
                killPython();
                @run(" /usr/bin/python3 ".getcwd().DIRECTORY_SEPARATOR."soc.py {$LDRdisable}");
                killPython();
                sleep(2);                    
                killPython();
                $line = "/usr/bin/python3 ".getcwd().DIRECTORY_SEPARATOR."soc.py  $command";
            }
            else
            {
                killPython();
                sleep(2);                    
                killPython();
                @run(" python3 ".getcwd().DIRECTORY_SEPARATOR."soc.py {$LDRdisable}");
                killPython();
                sleep(2);                    
                killPython();
                $line = "python3 ".getcwd().DIRECTORY_SEPARATOR."soc.py $command";
            }

            $cron .="\n".$line.' @';

            echo $cron."\n";

            $file = fopen("cron.json", "w+");
            fwrite($file, $cron."\n");
            fclose($file);

        }

    }
}


//########################################################################################################

function commandReport($status)
{
    try
    {
        $whois = getArtifactNumber();
        $whois = str_replace('"','',$whois);
        $url ="https://boe-php.eletromidia.com.br/RMC/Command/commandreport.php?csrf=".md5(time())."&whois=".$whois."&command=".urlencode($status);
        $result = file_get_contents($url);
        var_dump($result."\r\n");
    }
    catch(Exception $e)
    {
        logger($e->getMessage());
        commandReport($status);
    }
}

//##########################################################################################################

function run($command)
{
    @killPython();
    sleep(1);
    @killPython();
    if(PHP_OS== "Linux")
    {
       return shell_exec($command);
    }
    else
    {
        return exec($command);
    }
}


//####################################################################################################


function handshake($mac)
{
    try
    {
        $mac = str_replace('"','',$mac);
        $csrf = md5(time());
        $url="https://boe-php.eletromidia.com.br/RMC/Main/Deploy/handshake.php";
        $query = http_build_query(array('csrf' => $csrf , 'mac'=> $mac));
        $result = file_get_contents($url . '?' . $query);
        return $result;
    }
    catch(Exception $e)
    {
        echo $e->getMessage();
    }
}

function notify($whois , $log)
{
    try
    {
        $whois = str_replace('"','',$whois);
        $log = str_replace(';', '', $log);

        $URL= 'https://boe-php.eletromidia.com.br/RMC/Log/logs.php';
        $postdata =http_build_query(
            array(
                'csrf' => md5(time()),
                'whois' => $whois,
                'status' => $log
                )
        );

        $opts = array('http' =>
                array(
                    'method'  => 'POST',
                    'header'  => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $postdata
                )
        );

        $context  = stream_context_create($opts);
        $result = file_get_contents( $URL, false, $context );
        echo "notify\r\n";
        return $result;
    }
    catch(Exception $e)
    {
        var_dump($e->getMessage());
    }
}

function registerArtifactHw($artifact, $hardware, $macaddres)
{
   try
   {
        $url="https://boe-php.eletromidia.com.br/RMC/Hardware/hwinfo.php";
        $artifact = str_replace('"','', $artifact);

        $postdata =http_build_query(
                array(
                    'hwinfo' => json_encode( $hardware ),
                    'csrf' => md5(time()),
                    'artifact' => $artifact,
                    'macaddress'=> $macaddres
                )
        );

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context  = stream_context_create($opts);
        $result = file_get_contents( $url, false, $context );
        var_dump($result);
   }
   catch(Exception $e)
   {
        registerArtifactHw($artifact, $hardware, $macaddres);
   }
}

function logger($texto)
{
   try
   {
	   $url ='https://boe-php.eletromidia.com.br/RMC/Log/logs.php';

       $whois = getArtifactNumber();
       $whois = str_replace('"','',$whois);

        $postdata =http_build_query(
                array(
                    'csrf' => md5(time()),
                    'whois' => $whois,
                    'status'=> $texto
                )
        );

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context  = stream_context_create($opts);
        $result = file_get_contents( $url, false, $context );
        var_dump($result."\r\n");
   }
   catch(Exception $e)
   {
	   logger($e->getMessage());
	   logger($texto);
   }
}

function sendData($memory, $cpu, $hdd,$type, $whois, $temp)
{
    try
    {
        $postdata = http_build_query(
            array(
                'memory' => $memory,
                'cpu' => $cpu,
                'hdd' => $hdd,
                'tipo' => $type,
                'csrf' => md5(time()),
                'whois'=> $whois,
                'temp' => $temp
             )
        );

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );
        $context  = stream_context_create($opts);
        $result = file_get_contents('https://boe-php.eletromidia.com.br/RMC/Hardware/nucreport.php', false, $context);
        var_dump($result."\r\n");
    }
    catch(\Exception $e)
    {
        @sendData($memory, $cpu, $hdd,$type, $whois);
    }
}

//####################################################################################################

?>