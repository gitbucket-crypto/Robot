<?php

echo $argv[1];

define('bash',$argv[1]);



function downloadFile($url, $path)
{
    $newfname = $path;
    if(file_exists($path))
    {
        unlink($path);
    }
    $file = fopen ($url, 'rb');
    if ($file) {
        $newf = fopen ($newfname, 'wb');
        if ($newf) {
            while(!feof($file)) {
                fwrite($newf, fread($file, 1024 * 8), 1024 * 8);
            }
        }
    }
    if ($file) {
        fclose($file);
    }
    if ($newf) {
        fclose($newf);
    }
}

function installENV()
{   
    if(PHP_OS == "Linux")
    {
        $uname = shell_exec(' uname -v');
        if(strpos($uname, '6.1.69')==false)
        {
            echo 'kernel antigo '.PHP_EOL;

            $sys =   shell_exec(bash.'&& sudo dpkg -l sysstat');
            if(strpos($sys, 'dpkg-query: nãoo foram encontrados pacotes coincidindo com')!=false)
            {
                echo 'dpkg nãoo achou sysstat 12.5 '.PHP_EOL;
                downloadFile('http://ftp.de.debian.org/debian/pool/main/s/sysstat/sysstat_12.5.2-2_amd64.deb',getcwd().DIRECTORY_SEPARATOR.'sysstat_12.5.2-2_amd64.deb');
                shell_exec(bash.'&& sudo apt install ./sysstat_12.5.2-2_amd64.deb  -y');
            }            
        }
        else
        {
            $sys =   shell_exec(bash.'&& sudo dpkg -l sysstat');
            if(strpos($sys, 'dpkg-query: nãoo foram encontrados pacotes coincidindo com')!=false)
            {
                shell_exec(bash.' && sudo apt-get install sysstat -y');
            }      
        }

        $lsw = shell_exec(bash.'&& sudo dpkg -l lshw');
        if(strpos($lsw, 'dpkg-query: nãoo foram encontrados pacotes coincidindo com')!=false)
        {
            shell_exec(bash.' && sudo apt-get install lshw -y');
        }

        $lms = shell_exec(bash.'&& sudo dpkg -l lm-sensors');
        if(strpos($lms, 'dpkg-query: nãoo foram encontrados pacotes coincidindo com')!=false)
        {
            shell_exec(bash.' && sudo apt-get install lm-sensors -y');
        }

        $pip= shell_exec(bash.'&& sudo dpkg -l python3-pip');
        if(strpos($piip, 'dpkg-query: não foram encontrados pacotes coincidindo com')!=false)
        {
            shell_exec(bash.' && sudo apt-get install python3-pip -y');
        }
    
       shell_exec(bash.'&& sudo rm -rf /usr/lib/'.python.'/EXTERNALLY-MANAGED');
       shell_exec(bash.'&& sudo touch EXTERNALLY-MANAGED');

       $pip = shell_exec(python.' -m pip freeze');
       if(find('requests', $pip)==false)
       {
            shell_exec(bash.'&& '.python.' -m pip install requests');
            shell_exec(bash.'&& '.python.' -m pip install requests --user');

       }
       if(find('selenium', $pip)==false)
       {
            shell_exec(bash.'&& '.python.' -m pip selenium ');
            shell_exec(bash.'&& '.python.' -m pip selenium --user');

       }
       if(find('speedtest-cli', $pip)==false)
       {
            shell_exec(bash.'&& '.python.' -m pip speedtest-cli ');
            shell_exec(bash.'&& '.python.' -m pip speedtest-cli --user');
       }  
    }
    else
    {
        exec(python.' -m pip install --upgrade pip');
        exec(python.' -m pip install --upgrade virtualenv');
        exec(python.' -m pip install requests selenium ');
        exec(python.' -m pip install requests selenium --user');
    }
}

function find($needle, $haystack)
{
    if ($needle !== '' && str_contains($haystack, $needle)) {
        echo "This returned true!".PHP_EOL;
        return true;
    }
    else 
    {
        echo "This returned false!".PHP_EOL;
        return false;
    }
}

if (!function_exists('str_contains')) 
{
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

#################################################################################################
exit;
?>