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
if (1 != ($status = include dirname(__FILE__).'/_merge_base.php'))
{
    return $status;
}

require_once dirname(__FILE__). '/_log_base.php';

function acceptLogEntry($log_entry, DomXPath $log_path, array $parameters = array())
{
    if (empty($log_entry))
    {
        return false;
    }

    $revision = $log_entry->getAttribute('revision');

    if (!in_array($revision, $parameters['__avail_revisions']))
    {
        return false;
    }

    if (!empty($parameters['__target_path']))
    {
        $nodes = $log_path->query('paths/path[starts-with(text(), \'' . $parameters['__target_path'] . '\')]', $log_entry);
        if (empty($nodes) || 0 == $nodes->length)
        {
            return false;
        }
    }

    $message = trim($log_path->query('msg', $log_entry)->item(0)->nodeValue, " \n");
    if (!empty($parameters['search']) && false === stripos($message, $parameters['search']))
    {
        return false;
    }

    return true;
}

function compactRevisions(array $revisions)
{
    $retval = '';
    
    $last_revision = false;
    $is_block = false;

    foreach ($revisions as $revision)
    {
        if (false === $last_revision)
        {
            $retval = $revision;
        }
        elseif ($last_revision + 1 == $revision)
        {
            $is_block = true;
        }
        elseif (true === $is_block)
        {
            $retval .= '-' . $last_revision . ',' . $revision;
            $is_block = false;
        }
        else
        {
            $retval .= ',' . $revision;
        }

        $last_revision = $revision;
    }

    if (true === $is_block)
    {
        $retval .= '-' . $last_revision;
    }

    return $retval;
}

$info = "Listing%s mergeable revisions";

if (!empty($parameters['search']))
{
    $info .= ', searching for ' . $parameters['search'];
    /*
     * no limit when searching
     */
    $parameters['limit'] = false;
}

if (!empty($parameters['target']))
{
    $info .= ', affecting the target ' . $parameters['target'];
    /*
     * no limit for target filter
     */
    $parameters['limit'] = false;
}

if (!empty($parameters['limit']))
{
    $info = sprintf($info, ' latest ' . $parameters['limit']);
}
else
{
    $info = sprintf($info, '');
}

$info .= ".\n";

echo $info;

/*
 * fetch infos about the wc
 */
if ('de' === SVN_LANG)
{
    $base = $local_info['Basis des Projektarchivs'];
}
else
{
    $base = $local_info['Repository Root'];
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

$merge_data = explode(':', $merge_data);

$head_url = $base . $merge_data[0];
$head_info = getInfo($head_url);

if (empty($head_info))
{
    echo 'Error fetching information about the source of the branch. Tried: ' . $head_url . "\n";
    return -1;
}

$command = sprintf('svnmerge avail --force %s', getSubversionCredentialsParameter());

$output = array();
$return_status = 0;

exec($command, $output, $return_status);

if (empty($output))
{
    echo 'No revisions are available for merge.' . "\n";
    return 0;
}

$raw_revisions = explode(',', implode("\n", $output));
$revisions = array();
foreach ($raw_revisions as $revision)
{
    $split_revision = explode('-', $revision);
    if (!isset($split_revision[1]))
    {
        $revisions[] = $revision;
        continue;
    }

    for (; $split_revision[0] <= $split_revision[1]; $split_revision[0]++)
    {
        $revisions[] = (string)$split_revision[0];
    }
}

$parameters['__avail_revisions'] = $revisions;
$log = array();

$revision_range = reset($revisions) . ':' . end($revisions);
$verbose = '';
$parameters['__target_path'] = '';
if (!empty($parameters['target']))
{
    $target_url = $head_info['URL'] . '/' . $parameters['target'];
    $target_info = getInfo($target_url);
    if (!empty($target_info))
    {
	$base_key = SVN_LANG == 'en' ? 'Repository Root' : 'Basis des Projektarchivs';
        $parameters['__target_path'] = substr($target_info['URL'], strlen($target_info[$base_key]));
        $verbose = '-v';
    }
    else
    {
        echo 'Target ' . $target_url . ' does not exist. No mergeable revisions are available.' . "\n";
        return 0;
    }
}

$get_log_command = sprintf('svn log --non-interactive --xml %s -r%s %s %s', $verbose, $revision_range, getSubversionCredentialsParameter(), $head_url);

exec($get_log_command, $log);

$log = implode("\n", $log);
$log_doc = new DomDocument();
$log_doc->loadXml($log);

$log_path = new DomXPath($log_doc);

$log_entries = $log_doc->getElementsByTagName('logentry');

$output = parseLogEntries($log_entries, $log_path, $parameters);
$output_revisions = array_keys($output);
if (!empty($parameters['limit']))
{
    $output = array_slice($output, (-1 * $parameters['limit']));
    $output_revisions = array_slice($output_revisions, (-1 * $parameters['limit']));
}

if (!empty($output))
{
    echo 'Mergeable Revisions: ' . "\n" . compactRevisions($output_revisions) . "\n";
    echo 'Log:' . COLOR_SPLITTER;
    echo implode(COLOR_SPLITTER, $output) . COLOR_SPLITTER;
}
else
{
    echo 'No revisions are available for merge.' . "\n";
}
?>
