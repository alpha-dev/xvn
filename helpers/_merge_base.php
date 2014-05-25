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

function parseArguments($arguments)
{
    $retval = array(
        'revision' => '',
        'limit' => 10,
        'search' => '',
        'target' => '',
        'colorize' => true
    );

    $arg_count = count($arguments);
    for ($i = 1; $i < $arg_count; $i++)
    {
        $key = substr($arguments[$i], 0, 2);
        switch ($key)
        {
            case '-r':
                $retval['revision'] = $arguments[$i];
            break;
            case '-s':
                $retval['search'] = substr($arguments[$i], 2);
            break;
            case '-l':
                $limit = (int)substr($arguments[$i], 2);
                if (-1 == $limit)
                {
                    $retval['limit'] = false;
                }
                elseif (0 < $limit)
                {
                    $retval['limit'] = $limit;
                }
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

$parameters = parseArguments($argv);

/*
 * fetch infos about the wc
 */
$local_info = getInfo('.');
$repo_url = $local_info['URL'];

$repo_info = getInfo($repo_url);

if ($local_info['Revision'] != $repo_info['Revision'])
{
    /*
     * update
     */
    echo "Updating release copy\n";
    exec('svn -q --non-interactive up ' . getSubversionCredentialsParameter());
}

/*
 * fetch the mergetracking head
 */
$command = sprintf('svn pg --non-interactive svnmerge-integrated .');

$merge_data = exec($command);

if (empty($merge_data))
{
    echo 'Error fetching mergetracking information.' . "\n";
    return -1;
}

?>
