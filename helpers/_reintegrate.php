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
        'from'   => '1',
        'to'     => 'HEAD',
        'target' => '',
        'force'  => false
    );

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
            case '-f':
                $retval['force'] = true;
            break;
            case '--':
                /*
                 * not- abbriviated parameters
                 */
                switch ($arguments[$i])
                {
                    case '--force':
                        $retval['force'] = true;
                    break;
                }
            break;
            default:
                $retval['target'] = $arguments[$i];
            break;
        }
    }

    return $retval;
}

$parameters = parseArguments($argv);
$info = getInfo('.');

$trunk = $info['URL'];
if (false === $parameters['force'] && 'trunk' !== substr($trunk, strrpos($trunk, '/') + 1))
{
    echo 'Reintegrate only supports reintegrating to a trunk checkout' . "\n";
    return 1;
}

if (empty($parameters['target']))
{
    echo 'Missing branch to reintegrate.' . "\n";
    return 2;
}

$repo_info = getInfo($trunk);

if ($info['Revision'] != $repo_info['Revision'])
{
    /*  
     * update
     */
    echo "Updating working copy\n";
    exec(sprintf('svn -q --non-interactive %s up ', getSubversionCredentialsParameter()));
}

$repo_root = ('de' === SVN_LANG) ? $info['Basis des Projektarchivs'] : $info['Repository Root'];

$bases = array(
    substr($trunk, 0, strrpos($trunk, '/')),
    $repo_root
);

$branch_info = null;
$tried_locations = array();

foreach ($bases as $base)
{
    if ('/' === $parameters['target']{0})
    {
        $branch = $base . $parameters['target'];
        $branch_info  = getInfo($branch);
        $tried_locations[] = $branch;
    }
    else
    {
        $branch = $base . '/branches/' . $parameters['target'];

        $branch_info = getInfo($branch);
        $tried_locations[] = $branch;

        if (empty($branch_info))
        {
            $branch = $base . '/releases/' . $parameters['target'];
            $branch_info = getInfo($branch);
            $tried_locations[] = $branch;
        }
    }

    if (!empty($branch_info))
    {
        break;
    }
}

if (empty($branch_info))
{
    echo 'Branch / Release ' . $parameters['target'] . ' does not exist.' . "\n";
    echo 'Tried locations:' . "\n";
    echo implode("\n", $tried_locations) . "\n";
    return 3;
}

$source = '--reintegrate ' . $trunk;
$has_revision = false;
if ('1' !== $parameters['from'] || 'HEAD' !== $parameters['to'])
{
    $has_revision = true;
    $source = '-r' . $parameters['from'] . ':' . $parameters['to'];
}

$command = sprintf('svn merge --non-interactive --accept=postpone %s %s %s . 2>&1', getSubversionCredentialsParameter(), $source, $branch);

$return_status = 0;
$output = array();

echo "Reintegrating branch " . $branch . "\n";
exec($command, $output, $return_status);

if (0 != $return_status)
{
    echo 'Error during backmerge:' . "\nExecuted command:\n" . $command . "Output: \n" . implode("\n", $output) . "\n";
    return 4;
}

$command = 'svn pd svn:mergeinfo -R .';
exec($command);

$command = 'svn pd svnmerge-integrated .';
exec($command);

$command = 'svn pd svnmerge-blocked .';
exec($command);

$command = sprintf('_svnlog %s %s %s > reintegrate.log', getSubversionCredentialsParameter(), (true === $has_revision)?($source):(''), $branch);
exec($command);

echo 'Backmerge successful. You\'ll find the changelog of the branch in the file reintegrate.log' . "\n";
return 0;
?>
