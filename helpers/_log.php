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
require dirname(__FILE__).'/_base.php';
require_once dirname(__FILE__). '/_log_base.php';

function acceptLogEntry($log_entry, DomXPath $log_path, array $parameters = array())
{
    if (empty($log_entry))
    {
        return false;
    }

    $message = trim($log_path->query('msg', $log_entry)->item(0)->nodeValue, " \n");

    $lines = explode("\n", $message);

    if (!empty($parameters['authors']))
    {
        $author = trim($log_path->query('author', $log_entry)->item(0)->nodeValue);
        if (!in_array($author, $parameters['authors']))
        {
            return false;
        }
    }
    
    for ($i = 0; $i < count($lines); $i++)
    {
        if (true === $parameters['filterMergeMessages'] && false !== strpos($lines[$i], 'Merged revisions ') && false !== strpos($lines[$i], 'via svnmerge from'))
        {
            return false;
        }   

        if (!empty($parameters['search']) && false === stripos($lines[$i], $parameters['search']))
        {
            return false;
        }
    }

    return true;
}

function parseArguments($arguments)
{
    $retval = array(
        'from'   => '1',
        'to'     => 'HEAD',
        'lines'  => 10,
        'target' => '',
        'search' => '',
        'deep' => false,
        'colorize' => false,
        'limit' => false,
        'author' => false,
        'filterMergeMessages' => true
    );

    $arg_count = count($arguments);
    for ($i = 1; $i < count($arguments); $i++)
    {
        $key = substr($arguments[$i], 0, 2);
        switch ($key)
        {
            case '-r':
                $tmp = explode(':', substr($arguments[$i], 2));
                $retval['from'] = $tmp[0];
                if (isset($tmp[1]))
                {
                    $retval['to'] = $tmp[1];
                }
                else
                {
                    $retval['to'] = $retval['from'];
                    $retval['from']--;
                }
            break;
            case '-n':
                $retval['lines'] = (int)substr($arguments[$i], 2);
                if (0 == $retval['lines'])
                {
                    echo 'Invalid lines argument given. Has to be numeric.' . "\n";
                    $retval['lines'] = 10;
                }
            break;
            case '-s':
                $retval['search'] = substr($arguments[$i], 2);
            break;
            case '-d':
                $retval['deep'] = true;
            break;
            case '-c':
                $retval['colorize'] = true;
            break;
            case '-l':
                $retval['limit'] = (int)substr($arguments[$i], 2);
            break;
            case '-a':
                $retval['authors'] = explode(',', substr($arguments[$i], 2));
            break;
            case '+m':
                $retval['filterMergeMessages'] = false;
            break;
            default:
                if ('-' === $arguments[$i]{0})
                {   
                    $split_arg = explode('=', $arguments[$i], 2); 
                    if (!isset($split_arg[1]))
                    {   
                        $i++;
                        $split_arg[1] = $arguments[$i];
                    }   

                    if (in_array($split_arg[0], array('--username', '--password')))
                    {   
                        continue;
                    }   

                    echo 'Ignoring unknown parameter ' . $split_arg[0] . ".\n";
                    continue;
                }

                $retval['target'] = $arguments[$i];
            break;
        }
    }

    return $retval;
}


$log = array();

$parameters = parseArguments($argv);

$info = 'Fetching log for revisions ' . $parameters['from'] . ' to ' . $parameters['to'];
if (!empty($parameters['search']))
{
    $info .= ', searching for ' . $parameters['search'];
}
if (!empty($parameters['authors']))
{
    if (1 === count($parameters['authors']))
    {
        $info .= ', by author ' . implode(',', $parameters['authors']);
    }
    else
    {
        $info .= ', by authors ' . implode(', ', $parameters['authors']);
    }
}
if (true === $parameters['deep'])
{
    $info .= ', without stopping on copy operations';
}
if (!empty($parameters['limit']))
{
    $info .= ', limited to ' . $parameters['limit'] . ' log entries';
}

if (false === $parameters['filterMergeMessages'])
{
    $info .= ', including merge messages';
}

$svn_info = getInfo($parameters['target']);
if (empty($svn_info))
{
    echo 'The target ' . $parameters['target'] . ' does not exist.' . "\n";
    return -1;
}

if ('HEAD' === $parameters['to'])
{
   $parameters['to'] = $svn_info['Revision'];
}

$info .= ".\n";
echo $info;
exec(sprintf('svn log --non-interactive %s --xml -r%s:%s %s %s', ($parameters['deep'])?(''):('--stop-on-copy'), $parameters['from'], $parameters['to'], getSubversionCredentialsParameter(), $parameters['target']), $log);

$log = implode("\n", $log);

$log_doc = new DomDocument();
$log_doc->loadXml($log);

$log_path = new DomXPath($log_doc);

$log_entries = $log_doc->getElementsByTagName('logentry');

$output = parseLogEntries($log_entries, $log_path, $parameters);
if (!empty($parameters['limit']))
{
    $output = array_slice($output, (-1 * $parameters['limit']));
}

$splitter = SPLITTER;

if (true === $parameters['colorize'])
{
    $splitter = COLOR_SPLITTER;
}

echo implode($splitter, $output) . $splitter;

?>
