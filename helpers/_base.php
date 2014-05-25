<?php
/*
# -*- coding: utf-8 -*-
# Copyright (c) 2014, Sven Kretschmann
# All rights reserved.
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA
*/
if (defined('#_base.php'))
{
    return 0;
}

define('#_base.php', true);

require_once dirname(__FILE__).'/env.php';

function read_input($prompt)
{
    echo $prompt;
    $fp    = fopen("/dev/stdin", "r");
    $input = trim(fgets($fp, 255));
    fclose($fp);

    return $input;
}

function getSubversionCredentials()
{
    static $credentials = null;

    if (null === $credentials)
    {
        require dirname(__FILE__).'/user_creds.php';
        $credentials = array();
        $credentials['user'] = SVN_USERNAME;
        $credentials['password'] = SVN_PASSWORD;
    }

    return $credentials;
}

function getSubversionUser()
{
    $credentials = getSubversionCredentials();
    return $credentials['user'];
}

function getSubversionPassword()
{
    $credentials = getSubversionCredentials();
    return $credentials['password'];
}

function getSubversionCredentialsParameter()
{
    return sprintf('--username=\'%s\' --password=\'%s\'', getSubversionuser(), getSubversionPassword());
}

function getInfo($url)
{
    $output = array();
    $return_status = 0;

    $creds = '';
    if (!file_exists($url)) {
        $creds = getSubversionCredentialsParameter();
    }

    $command = sprintf('svn info --non-interactive %s %s 2>&1', $creds, $url);

    exec($command, $output, $return_status);

    $no_valid_url_string = ('de' === SVN_LANG) ? '(Keine gültige URL)'  : '(Not a valid URL)';

    if (0 != $return_status || false !== strpos(reset($output), $no_valid_url_string))
    {   
        return array();
    }   

    $info = array();
    foreach ($output as $line)
    {   
        if ('' == trim($line))
        {   
            continue;
        }   
        $explode = explode(':', $line, 2); 
        $key = trim($explode[0]);
        $info[$key] = trim($explode[1]);
    }   

    return $info;
}

/**
 * Will return the info from the revision before the file was deleted
 */
function getDeletedFileInfo($directory, $filename)
{
    $dir_info = getInfo($directory);
    if (!empty($dir_info['URL']))
    {
        $path = substr($dir_info['URL'], strlen($dir_info['Repository Root'])) . '/' . $filename;
        $target_url = $dir_info['URL'] . '/' . $filename;
        $log = array();
        exec(sprintf('svn log --non-interactive %s --xml --verbose %s 2>&1', getSubversionCredentialsParameter(), $dir_info['URL']), $log, $status);

        if (0 !== $status)
        {
            //echo 'An error occured during fetch of the history:' . "\n";
            //echo implode("\n", $log) . "\n";
            //return -1;
            return array();
        }

        $log = implode("\n", $log);
        $log_dom = new DOMDocument();
        if (!@$log_dom->loadXml($log))
        {
            //echo 'Invalid xml response received:' . "\n";
            //echo $log . "\n";
            //return -2;
            return array();
        }

        $xpath = new DOMXpath($log_dom);
        $nodes = $xpath->query('//path[@action="D"][. = "' . $path . '"]');

        if (0 < $nodes->length)
        {
            $delete_revision = ($nodes->item(0)->parentNode->parentNode->getAttribute('revision') - 1);
            $target_url .= '@' . $delete_revision;

            $info = getInfo($target_url);

            if (!empty($info))
            {
                return $info;
            }
        }
    }
    return array();
}
?>
