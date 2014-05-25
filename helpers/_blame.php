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
        'from'    => '1',
        'to'      => 'HEAD',
        'target'  => '',
        'lines'   => false,
    );

    $context = 15;
    $lines = array();

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
                    $retval['from'] = 1;
                }   
            break;
            case '-l':
                $tmp = explode(':', substr($arguments[$i], 2));
                $lines = array('from' => (int)$tmp[0]);
                if (isset($tmp[1]))
                {
                    $lines['to'] = (int)$tmp[1];
                }

            break;
            case '-n':
                $context = (int)substr($arguments[$i], 2);
                if (0 === $context)
                {
                    trigger_error('The provided number of contextual lines is no integer.', E_USER_ERROR);
                }
            break;
            default:
                $retval['target'] = $arguments[$i];
            break;
        }
    }

    if (!empty($lines))
    {
        if (!isset($lines['to']))
        {
            $lines['from'] -= $context;
            $lines['to'] = $lines['from'] + ($context * 2);
            $lines['from'] = max($lines['from'], 1);
        }

        if (0 >= $lines['from'] || 0 >= $lines['to'])
        {
            trigger_error('The provided line number is no positive integer.', E_USER_ERROR);
        }

        if ($lines['from'] > $lines['to'])
        {
            trigger_error('The from line number is greater than the to line number.', E_USER_ERROR);
        }

        $retval['lines'] = array(
            'offset' => $lines['from'] - 1,
            'count'  => $lines['to'] - $lines['from']
        );
    }

    if (empty($retval['target']))
    {
        trigger_error('No target provided.', E_USER_ERROR);
    }

    return $retval;
}

function extractNodeValue($node)
{
    return $node->nodeValue;
}

function extractRevision($node)
{
    return $node->getAttribute('revision');
}

function getMaxLength($strings)
{
    $max = 0;
    foreach ($strings as $string)
    {
        if (strlen($string) > $max)
        {
            $max = strlen($string);
        }
    }

    return $max;
}

$parameters = parseArguments($argv);

$info = getInfo($parameters['target']);

if (empty($info['URL']))
{
    echo 'File ' . $parameters['target'] . ' does not exist. Trying to find the delete revision.' . "\n";

    $info = getDeletedFileInfo('.', $parameters['target']);

    if (!empty($info['URL']))
    {
        $parameters['target'] = $info['URL'] . '@' . $info['Revision'];
        echo 'The file was deleted in revision ' . ($info['Revision'] + 1) . '. Using ' . $parameters['target'] . " as source.\n";
    }

    if (empty($info))
    {
        echo 'File ' . $parameters['target'] . ' does not exist.' . "\n";
        return -1;
    }
}

if ('HEAD' === $parameters['to'])
{
    $parameters['to'] = $info['Revision'];
}

$blame = array();
$status = 0;
exec(sprintf('svn blame --non-interactive %s --xml -r%s:%s %s@%d 2>&1', getSubversionCredentialsParameter(), $parameters['from'], $parameters['to'], $info['URL'], $info['Revision']), $blame, $status);

if (0 !== $status)
{
    echo 'An error occured during fetch of the file contents:' . "\n";
    echo implode("\n", $blame) . "\n";
    return -1;
}

$blame = implode("\n", $blame);

$blame_doc = new DomDocument();
$blame_doc->loadXml($blame);

$blame_path = new DomXPath($blame_doc);

$blame_authors = $blame_doc->getElementsByTagName('author');

$line_authors = array();
foreach ($blame_authors as $author)
{
    $line_authors[] = $author->nodeValue;
}

$authors = array_unique($line_authors);

$author_length = getMaxLength($authors) + 1;

$lines = $blame_doc->getElementsByTagName('entry');

$raw_lines = array();
$revision_length = 0;
foreach ($lines as $line)
{
    $raw_line = array();

    $commit = $line->firstChild->nextSibling;

    $raw_line['revision'] = $commit->getAttribute('revision');
    $raw_line['author'] = $commit->firstChild->nextSibling->nodeValue;

    $raw_lines[$line->getAttribute('line-number')] = $raw_line;

    if ($raw_line['revision'] > $revision_length)
    {
        $revision_length = $raw_line['revision'];
    }
}

$revision_length = strlen($revision_length);
$line_length = strlen(max(array_keys($raw_lines)));

$line_pattern = '%' . $revision_length . 'd | %-' . $author_length . 's | %' . $line_length . 'd: %s' . "\n";
$content = array();

exec(sprintf('svn cat --non-interactive %s -r%s %s@%d', getSubversionCredentialsParameter(), $parameters['to'], $info['URL'], $info['Revision']), $content);

if (!empty($parameters['lines']))
{
    $max_line = count($raw_lines);
    if ($parameters['lines']['offset'] > $max_line)
    {
        echo "No line with the given number exists in the file. The file has " . ($max_line) . " lines.\n";
        return 1;
    }

    $raw_lines = array_slice($raw_lines, $parameters['lines']['offset'], $parameters['lines']['count'], true);
}

$output = array();
foreach ($raw_lines as $index => $line)
{
    $line = sprintf($line_pattern, $line['revision'], $line['author'], $index, rtrim($content[$index - 1]));
    $output[] = $line;
}

echo implode("", $output) . "\n";
?>
