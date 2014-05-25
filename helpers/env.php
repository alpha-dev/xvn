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
@include_once dirname(__FILE__).'/../conf/env.php';

if (!defined('SVN_LANG'))
{
    $output = array();

    exec('svn --version', $output);

    $output = implode("\n", $output);

    if (false !== strpos($output, 'übersetzt'))
    {
        define('SVN_LANG', 'de');
    }
    else
    {
        define('SVN_LANG', 'en');
    }
}

?>
