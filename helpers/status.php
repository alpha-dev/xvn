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
require_once dirname(__FILE__).'/_base.php';

function parseArguments($arguments)
{
    $retval = array(
        'target' => '',
        'ignore-externals' => false,
        'exclude' => '',
        'group_added' => 10,
	'exclude_changelisted' => false
    );  

    for ($i = 1; $i < count($arguments); $i++)
    {
        if ('--ignore-externals' == $arguments[$i])
        {
            $retval['ignore-externals'] = true;
        }
        else
        {
            $key = substr($arguments[$i], 0, 2); 
            switch ($key)
            {
                case '-e':
                    $retval['exclude'] = substr($arguments[$i], 2);
                break;
                case '-g':
                    $retval['group_added'] = substr($arguments[$i], 2);
                break;
                case '-n':
                    if ('-nocl' === $arguments[$i])
                    {
                        $retval['exclude_changelisted'] = true;
                    }
                break;
                default:
                    $retval['target'] .= ' ' . $arguments[$i];
                break;
            }
        }
    }

    if (empty($retval['target']))
    {
        $retval['target'] = '.';
    }
    else
    {
        $retval['target'] = trim($retval['target']);
    }

    return $retval;
}

function filterRemoved($item)
{
    if ('--removed--' == $item)
    {
        return false;
    }

    return true;
}

function filterUnadded($line)
{
    if (!empty($line) && '?' == $line{0})
    {
        return true;
    }

    return false;
}

function markExcluded($items, $exclude_pattern)
{
    if (empty($exclude_pattern))
    {   
        return $items;
    }   

    $exlude_current = false;
    $line_numbers = array_keys($items);

    $file_pattern = '/^.*\s(.+)$/';
    if (substr($exclude_pattern, 0, 1) !== substr($exclude_pattern, -1))
    {
        $exclude_pattern = '/^.*' . preg_quote($exclude_pattern, '/') . '.*$/';
    }
    foreach ($line_numbers as $line_number)
    {   
        $matches = array();
        if (preg_match($file_pattern, $items[$line_number], $matches))
        {   
            $file_name = $matches[1];
            $exclude_matches = array();
            if (preg_match($exclude_pattern, $matches[1], $exclude_matches))
            {   
                $items[$line_number] = '--removed--';
            }   
        }
    }
    return $items;
}

function markGrouped($items, $group_size_threshold)
{
    $containers = array();
    $container_counts = array();

    $line_numbers = array_keys($items);

    $container_sub_lines = array();

    foreach ($line_numbers as $line_number)
    {
        $line = $items[$line_number];
        if (empty($line))
        {
            continue;
        }

        if ('A' === $line{0} || 'D' === $line{0})
        {
            if ('A' === $line{0})
            {
                $dir = trim($line, 'A ') . '/';
            }
            else
            {
                $dir = trim($line, 'D ') . '/';
            }
            $container_indexes = array_keys($containers);
            $container_count = 0;
            foreach ($container_indexes as $index)
            {
                $container = $containers[$index];
                if (0 === strpos($dir, $container))
                {
                    $container_sub_lines[$container][] = $line_number;
                    $container_counts[$index]++;
                    continue 2;
                }
                elseif (0 === strpos($container, $dir))
                {
                    $container_sub_lines[$dir] = $container_sub_lines[$container];
                    unset($container_sub_lines[$container]);
                    $container_sub_lines[$dir][] = $index;
                    unset($containers[$index]);
                    $container_count = $container_counts[$index] + 1;
                    unset($container_counts[$index]);
                }
            }
            $containers[$line_number] = $dir;
            $container_counts[$line_number] = $container_count;
            $container_sub_lines[$dir] = array();
        }
    }

    foreach ($container_counts as $line_number => $count)
    {
        if (!empty($count))
        {
            if ($count >= $group_size_threshold)
            {
                foreach ($container_sub_lines[$containers[$line_number]] as $sub_line_number)
                {
                    $items[$sub_line_number] = '--removed--';
                }
                $items[$line_number] .= ' + ' . $count . ' subitems';
            }
        }
    }

    return $items;
}

function markChangeListed($items)
{
    $line_numbers = array_keys($items);
    $exclude_count = 0;

    $file_pattern = '/^[^-].......(.*)$/';

    foreach ($line_numbers as $line_number)
    {
        $matches = array();
        if (preg_match($file_pattern, $items[$line_number], $matches))
        {
            $file_name = $matches[1];

            $info = getInfo($file_name);

            if (!empty($info['Changelist']) || !empty($info['Änderungsliste']))
            {
                $items[$line_number] = '--removed--';
                $exclude_count++;
            }
        }
        elseif ('--- ' === substr($items[$line_number], 0, 4))
        {
            $items[$line_number] = '--removed--';
        }
        elseif ('' === trim($items[$line_number]))
        {
            $items[$line_number] = '--removed--';
        }
    }

    if (0 < $exclude_count)
    {
        $items[$line_number + 1] = '';
        $items[$line_number + 2] = "\033[33m" . $exclude_count . " changelisted items excluded.\033[0m";
    }

    return $items;
}

function colorizeLine($line)
{
    if (empty($line))
    {
        return $line;
    }

    switch ($line{0})
    {
        case 'X':
            $line = "\033[30;1m" . $line . "\033[0m";
            break;
        case 'M':
            $line = "\033[33m" . $line . "\033[0m";
            break;
        case 'D':
            $line = "\033[31m" . $line . "\033[0m";
            break;
        case '!':
            $line = "\033[1;31m" . $line . "\033[0m";
            break;
        case 'C':
            $line = "\033[1;31m" . $line . "\033[0m";
            break;
        case 'A':
            $line = "\033[36m" . $line . "\033[0m";
            break;
        case ' ':
            switch ($line{1})
            {
                case 'M':
                    $line = "\033[33m" . $line . "\033[0m";
                    break;
            }
            break;
    }

    return $line;
}

$parameters = parseArguments($argv);

$output = array();

$only_locals = true;
foreach (explode(' ', $parameters['target']) as $target)
{
    if (!file_exists($target))
    {
        $only_locals = false;
        break;
    }
}

$command = 'svn st --non-interactive ';

if (true !== $only_locals)
{
    $command .= getSubversionCredentialsParameter() . ' ';
}

if (true === $parameters['ignore-externals'])
{
    $command .= '--ignore-externals ';
}

$command .= $parameters['target'];

exec($command, $output);

$status_data = array();

if (true === $parameters['ignore-externals'])
{
    $status_data = $output;
}
else
{
    $externals = array();
    $external_patterns = array();

    $ext_search = '/^Performing status on external item at \'(.*)\'$/';

    if ('de' == SVN_LANG)
    {
        $ext_search = '/^Hole Status des externen Verweises in »(.*)«$/';
    }

    foreach ($output as $line)
    {
        $matches = array();

        preg_match($ext_search, $line, $matches);

        if (!empty($matches[1]))
        {
            $externals[] = $matches[1];
            $external_patterns[] = "`\?\s+" . $matches[1] . '$`';
        }

        $status_data[] = $line;
    }

    foreach (array_filter($status_data, 'filterUnadded') as $line )
    {
        $dir = trim($line, '? ');

        if (preg_grep('`' . $dir . '/.*`', $externals))
        {
            $externals[] = $dir;
            $external_patterns[] = "`\?\s+" . $dir . '$`';
        }
    }

    $status_data = array_filter(preg_replace($external_patterns, '--removed--', $status_data), 'filterRemoved');

    $line_numbers = array_keys($status_data);
    $filter_ext = true;
    $ext_begin = -1;
    foreach ($line_numbers as $line_number)
    {
        $line = trim($status_data[$line_number]);
        $matches = array();
        preg_match($ext_search, $line, $matches);

        if (!empty($matches[1]))
        {
            if (-1 < $ext_begin && $filter_ext)
            {
                for ($l = $ext_begin; $l < $line_number; $l++)
                {
                    $status_data[$l] = '--removed--';
                }
            }

            $filter_ext = true;
            $ext_begin = $line_number;
        }
        elseif (-1 < $ext_begin && !empty($line))
        {
            $filter_ext = false;
        }
    }

    if (-1 < $ext_begin && $filter_ext)
    {
        $status_data[$ext_begin] = '--removed--';
    }

    $status_data = array_filter($status_data, 'filterRemoved');
}

if (!empty($status_data))
{
    $filter = false;
    if (!empty($parameters['exclude']))
    {
        $filter = true;
        $status_data = markExcluded($status_data, $parameters['exclude']);
    }
    if (!empty($parameters['group_added']))
    {
        $filter = true;
        $status_data = markGrouped($status_data, $parameters['group_added']);
    }
    if ($parameters['exclude_changelisted'])
    {
        $status_data = markChangeListed($status_data);
    }

    if (true === $filter)
    {
        $status_data = array_filter($status_data, 'filterRemoved');
    }
    echo implode("\n", array_map('colorizeLine', $status_data)) ."\n";
}
?>
