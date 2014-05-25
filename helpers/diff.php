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

function colorizeLine($line)
{
    static $last_file_deleted = false;

    switch (substr($line, 0, 1))
    {
        case '+':
            $line = "\033[32m" . $line . "\033[0m";
            break;
        case '-':
            if ('--removed--' !== $line)
            {
                $line = "\033[31m" . $line . "\033[0m";
            }
            break;
        case '=':
            if ('' == str_replace('=', '', $line))
            {
                if (true === $last_file_deleted)
                {
                    $last_file_deleted = false;
                    return '--removed--';
                }

                $line = "\033[36m" . $line . "\033[0m";
            }
            break;
        case 'I':
        case 'P':
            if ('Index:' == substr($line, 0, 6) || 'Property:' == substr($line, 0, 9) || 'Property changes on:' == substr($line, 0, 20))
            {
                if ('(deleted)' == substr($line, -9))
                {
                    $line = "\033[31m" . $line . "\033[m";
                    $last_file_deleted = true;
                }
                else
                {
                    $line = "\033[36m" . $line . "\033[0m";
                }
            }
            break;
    }

    return $line;
}

function markChangeListed($items, $verbose)
{
    $exclude_current = false;
    $line_numbers = array_keys($items);
    $exclude_count = 0;

    $file_pattern = '/^Index:\s([\/\_\w\d\.\-]+)(\s\(deleted\))?$/';

    foreach ($line_numbers as $line_number)
    {
        switch (substr($items[$line_number], 0, 1))
        {
            case 'I':
                $matches = array();
                if (preg_match($file_pattern, $items[$line_number], $matches))
                {
                    $file_name = $matches[1];

                    $info = getInfo($file_name);

                    if (!empty($info['Changelist']) || !empty($info['Änderungsliste']))
                    {
                        $exclude_current = true;
                        if ($verbose)
                        {
                            $items[$line_number] = "\033[33m" . $items[$line_number] . " (Excluded from diff)\033[0m";
                        }
                        else
                        {
                            $items[$line_number] = '--removed--';
                        }
                        $exclude_count++;
                    }
                    else
                    {
                        $exclude_current = false;
                    }
                }
            break;
            default:
                if (true === $exclude_current)
                {
                    $items[$line_number] = '--removed--';
                }
            break;
       }
    }

    if (!$verbose && 0 < $exclude_count)
    {
        $items[] = "\033[33m" . $exclude_count . " changelisted items excluded.\033[0m";
    }

    return $items;
}

function markExcluded($items, $exclude_pattern, $verbose)
{
    if (empty($exclude_pattern))
    {
        return $items;
    }

    $exlude_current = false;
    $line_numbers = array_keys($items);

    $file_pattern = '/^Index:\s([\/\_\w\d\.\-]+)(\s\(deleted\))?$/';
    if (substr($exclude_pattern, 0, 1) !== substr($exclude_pattern, -1))
    {
        $exclude_pattern = '/^.*' . preg_quote($exclude_pattern, '/') . '.*$/';
    }

    $exclude_count = 0;

    foreach ($line_numbers as $line_number)
    {
        switch (substr($items[$line_number], 0, 1))
        {
            case 'I':
                $matches = array();
                if (preg_match($file_pattern, $items[$line_number], $matches))
                {
                    $file_name = $matches[1];
                    $exclude_matches = array();
                    if (preg_match($exclude_pattern, $matches[1], $exclude_matches))
                    {
                        $exclude_current = true;
                        if ($verbose)
                        {
                            $items[$line_number] = "\033[33m" . $items[$line_number] . " (Excluded from diff)\033[0m";
                        }
                        else
                        {
                            $items[$line_number] = '--removed--';
                        }
                        $exclude_count++;
                    }
                    else
                    {
                        $exclude_current = false;
                    }
                    break;
                }
           default:
                if (true === $exclude_current)
                {
                    $items[$line_number] = '--removed--';
                }
        }
    }
    if (!$verbose && 0 < $exclude_count)
    {
        $items[] = "\033[33m" . $exclude_count . " items excluded from diff by pattern '" . $exclude_pattern . "'.\033[0m";
    }
    return $items;
}

function buildDiffForProperties($items)
{
    $property_texts = array();
    $current_property_path = '';
    $is_property_content = false;

    $line_numbers = array_keys($items);
    $retval = array();
    foreach ($line_numbers as $line_number)
    {
        $is_property_opener = false;
        $last_line_is_property_content = $is_property_content;
        if ('Eigenschaftsänderungen:' === substr($items[$line_number], 0, 24) || 'Property changes on:' == substr($items[$line_number], 0, 20))
        {
            $is_propert_opener = true;
            $is_property_content = true;
            if (!empty($property_texts))
            {
                $retval = array_merge($retval, reducePropertyLinesByDiff($property_texts));
                $property_texts = array();
            }
        }
        elseif ('Index:' == substr($items[$line_number], 0, 6))
        {
            $is_property_content = false;
            if (!empty($property_texts))
            {
                $retval = array_merge($retval, reducePropertyLinesByDiff($property_texts));
                $property_texts = array();
            }
        }
        

        if (true === $is_property_opener)
        {
            continue;
        }
        elseif (true === $is_property_content)
        {
            $property_texts[] = $items[$line_number];
            continue;
        }
        else
        {
            $retval[] = $items[$line_number];
        }
    }

    if (!empty($property_texts))
    {
        $retval = array_merge($retval, reducePropertyLinesByDiff($property_texts));
    }

    return array_merge(array(), $retval);
}

function reducePropertyLinesByDiff($property_lines)
{
    $is_removed_block = false;
    $is_added_block = false;

    $property_parts = array(
        'removed' => array(),
        'added' => array()
    );

    $has_removed_block = true;

    if ('de' === SVN_LANG)
    {
        $path = substr($property_lines[0], 25);
        if ('Hinzugefügt:' === substr($property_lines[2], 0, 13))
        {
            $property = 'Property: ' . substr($property_lines[2], 14) . ' on ' . $path;
            $has_removed_block = false;
        }
        elseif ('Gelöscht:' === substr($property_lines[2], 0, 10))
        {
            $property = 'Property: ' . substr($property_lines[2], 11) . ' on ' . $path;
            if (isset($property_lines[3]) && empty($property_lines[3]))
            {
                return array($property . ' (deleted)');
            }
        }
        else
        {
            $property = 'Property: ' . substr($property_lines[2], 11) . ' on ' . $path;
        }
    }
    else
    {
        $path = substr($property_lines[0], 21);
	if ('Added: ' === substr($property_lines[2], 0, 7))
	{
            $property = 'Property: ' . substr($property_lines[2], 7) . ' on ' . $path;
            $has_removed_block = false;
	}
        elseif ('Deleted: ' === substr($property_lines[2], 0, 9))
        {
            $property = 'Property: ' . substr($property_lines[2], 9) . ' on ' . $path;
            if (isset($property_lines[3]) && empty($property_lines[3]))
            {
                return array($property . ' (deleted)');
            }
        }
        else
        {
            $property = 'Property: ' . substr($property_lines[2], 10) . ' on ' . $path;
        }
   }

    foreach ($property_lines as $property_line)
    {
        $property_line = trim($property_line);

        if ($is_removed_block)
        {
            if ('+ ' === substr($property_line, 0, 2))
            {   
                $is_removed_block = false;
                $is_added_block = true;
                $property_parts['added'][] = trim(substr($property_line, 2));
            }
            else
            {
                $property_parts['removed'][] = $property_line;
            }
        }
        elseif ($is_added_block)
        {
            $property_parts['added'][] = $property_line;
        }
        else
        {
            if ('- ' === substr($property_line, 0, 2))
            {
                $is_removed_block = true;
                $property_parts['removed'][] = trim(substr($property_line, 2));
            }
            elseif (!$has_removed_block && '+ ' === substr($property_line, 0, 2))
            {
                $is_added_block = true;
                $property_parts['added'][] = trim(substr($property_line, 2));
            }
        }
    }

    if (!empty($property_parts['added']) || !empty($property_parts['removed']))
    {
        $rm_file = tempnam(sys_get_temp_dir(), 'diff_removed_');
        $add_file = tempnam(sys_get_temp_dir(), 'diff_added_');

        file_put_contents($rm_file, implode("\n", $property_parts['removed']));
        file_put_contents($add_file, implode("\n", $property_parts['added']));

        $diff = array();
        exec('diff -iEbB -U2 ' . $rm_file . ' ' . $add_file, $diff);

        unlink($rm_file);
        unlink($add_file);

        array_splice($diff, 0, 2, array($property, '==================================================================='));
        return $diff;
    }

    return array();
}

function filterRemoved($item)
{
    if ('--removed--' == $item)
    {   
        return false;
    }   

    return true;
}

function parseArguments($arguments)
{
    $retval = array(
        'targets' => array(),
        'from'   => '',
        'to'     => '',
        'exclude' => '',
        'no_color' => false,
        'verbose' => false,
	'exclude_changelisted' => false
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
            case '-e':
                $retval['exclude'] = substr($arguments[$i], 2);
                if (substr($retval['exclude'], 0, 1) !== '/')
                {
                    $retval['exclude'] = '/^.*' . preg_quote($retval['exclude'], '/') . '.*$/';
                }
            break;
            case '-n':
                if ('-nc' === $arguments[$i])
                {
                    $retval['no_color'] = true;
                }
                elseif ('-nocl' === $arguments[$i])
                {
                    $retval['exclude_changelisted'] = true;
                }
                break;
            break;
            case '-v':
                $retval['verbose'] = true;
            break;
            default:
                $retval['targets'][] = $arguments[$i];
            break;
        }
    }

    if (array() === $retval['targets'])
    {
        $retval['targets'][] = '.';
    }

    return $retval;
}


$output = array();

$parameters = parseArguments($argv);

$command = 'svn diff --non-interactive --diff-cmd diff -x -iEbBu --no-diff-deleted';

$only_locals = true;
if (!empty($parameters['from']) && !empty($parameters['to']))
{
    $command .= sprintf(' -r%s:%s', $parameters['from'], $parameters['to']);
    $only_locals = false;
}

if (true === $only_locals)
{
    foreach ($parameters['targets'] as $target)
    {
        if (!file_exists($target))
        {
            $only_locals = false;
            break;
        }
    }
}

if (true !== $only_locals)
{
    $command .= ' ' . getSubversionCredentialsParameter();
}

$command .= ' ' . implode(' ', array_map('escapeshellarg', $parameters['targets']));
$status = null;

exec($command . ' 2>&1', $output, $status);

if (0 != $status)
{
    echo "\033[31mAn error ocurred:\033[0m\n";
    echo implode("\n", $output) . "\n";
}
else
{
    if (!empty($output))
    {
        while ('' === trim(reset($output)))
        {
            array_shift($output);
        }

        $output = buildDiffForProperties($output);
        $output = markExcluded($output, $parameters['exclude'], $parameters['verbose']);
        if ($parameters['exclude_changelisted'])
        {
            $output = markChangeListed($output, $parameters['verbose']);
        }

	if (false === $parameters['no_color'])
        {
            $output = array_map('colorizeLine', $output);
        }
        $output = array_filter($output, 'filterRemoved');
    }

    if (!empty($output))
    {
        echo implode("\n", $output) . "\n";
    }
    else
    {
        echo "\033[32mNo changes found.\033[0m\n";
    }
}

?>
