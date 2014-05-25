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
define('SPLITTER', "\n" . '------------------------------------------------------------------------' . "\n");
define('SPLITTER_LENGTH', strlen(SPLITTER));
define('COLOR_SPLITTER', "\n\033[1;36m" . '------------------------------------------------------------------------' . "\033[0m\n");

function indentString($string, $indent, $indent_first_line = true, $indent_char = ' ')
{
    $lines = explode("\n", $string);

    $function = create_function('$line', "return sprintf('%s%s', str_pad('" . $indent_char . "', " . $indent . ", '" . $indent_char . "'), \$line);");

    $lines = array_map($function, $lines);

    if (false === $indent_first_line && isset($lines[0]))
    {
        $lines[0] = trim($lines[0], $indent_char);
    }

    return implode("\n", $lines);
}

function parseLogEntries(DomNodeList $log_entries, DomXPath $log_path, array $parameters = array())
{
    $output = array();

    if (!isset($parameters['lines']))
    {
        $parameters['lines'] = -1;
    }

    foreach ($log_entries as $log_entry)
    {
        if (!acceptLogEntry($log_entry, $log_path, $parameters))
        {
            continue;
        }

        $message = trim($log_path->query('msg', $log_entry)->item(0)->nodeValue, " \n");

        $lines = explode("\n", $message);

        if (0 < $parameters['lines'])
        {
            $message = implode("\n", array_slice($lines, 0, $parameters['lines']));
        }

        /*
         * check reintegration logs, containing the splitter in the message
         */
        if (false !== strpos($message, SPLITTER))
        {
            if ($splitter == substr($message, (-1 * SPLITTER_LENGTH)))
            {
                $message = substr($message, 0, (-1 * SPLITTER_LENGTH));
            }

            $message = indentString($message, 3, false);
        }

        $revision = $log_entry->getAttribute('revision');
        $author   = $log_path->query('author', $log_entry)->item(0)->nodeValue;
        $date     = new DateTime($log_path->query('date', $log_entry)->item(0)->nodeValue);

        $output[$revision] = parseLogMessage($revision, $author, $date, $message, $parameters);
    }

    return $output;
}

function parseLogMessage($revision, $author, DateTime $date, $message, $parseParameters)
{
    $pattern = "r%d | %s | %s

%s";

    if (isset($parseParameters['colorize']) && true === $parseParameters['colorize'])
    {
        $pattern = str_replace('r%d', "\033[1;36mr%d\033[0m", $pattern);
    }

    return sprintf(
        $pattern,
        $revision,
        $author,
        $date->format('Y-m-d H:i:s'),
        indentString($message, 1, true, '> ')
    );
}

?>
