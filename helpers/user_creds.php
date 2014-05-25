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
$user_creds = explode("\n", file_get_contents(dirname(__FILE__).'/../conf/user_creds.sh'));

$php_creds = array();

$known_creds = array('SVN_USERNAME', 'SVN_PASSWORD');

$known_parameters = array(
    '--username' => 'SVN_USERNAME',
    '--password' => 'SVN_PASSWORD'
);

if (!isset($argv))
{
    global $argv;
}

if (isset($argv))
{
    foreach ($argv as $i => $argument)
    {
        $name = substr($argument, 0, 10);

        if (isset($known_parameters[$name]))
        {
            if (10 === strlen($argument))
            {
                $value = trim($argv[$i + 1], '"\'');
            }
            elseif ('=' === $argument{10})
            {
                $value = trim(substr($argument, 11), '"\'');
            }
            else
            {
                continue;
            }

            define($known_parameters[$name], $value);
        }
    }
}

$env_creds = array();
foreach ($known_creds as $cred)
{
    if (!empty($_ENV[$cred]))
    {
        $env_creds[$cred] = $_ENV[$cred];
    }
    elseif (!empty($_SERVER[$cred]))
    {
        $env_creds[$cred] = $_SERVER[$cred];
    }
}

foreach ($user_creds as $cred)
{
    if (empty($cred))
    {
        continue;
    }
    $split_cred = explode('=', trim($cred));

    if (in_array($split_cred[0], $known_creds) && !defined($split_cred[0]))
    {
        if (empty($split_cred[1]))
        {
            if (isset($env_creds[$split_cred[0]]))
            {
                $input = $env_creds[$split_cred[0]];
            }
            else
            {
                $prompt = 'Please enter the value for ' . $split_cred[0] . ': ';
                if ('SVN_USERNAME' === $split_cred[0])
                {
                    $prompt = 'Please enter your subversion username: ';
                }
                elseif ('SVN_PASSWORD' === $split_cred[0])
                {
                    $prompt = 'Please enter your subversion password: ';
                    $oldmodes=`stty -g`;
                    `stty -echo`;
                }

                $input = read_input($prompt);

                if ('SVN_PASSWORD' === $split_cred[0])
                {
                    `stty $oldmodes`;
                    echo "\n";
                }

                if (empty($input))
                {
                    throw new Exception('You must provide a value for ' . $split_cred[0] . '.');
                }
            }
        }
        else
        {
            $input = $split_cred[1]; 
        }

        define($split_cred[0], trim($input, '"'));
    }
}

?>
