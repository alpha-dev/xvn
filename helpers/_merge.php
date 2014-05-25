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

if (!empty($parameters['revision']))
{
    echo "Merging revision(s) " . substr($parameters['revision'], 2) . "\n";
}
else
{
    echo "Merging all available revisions\n";
}

$command = sprintf('svnmerge merge --force %s %s', getSubversionCredentialsParameter(), $parameters['revision']);
passthru($command);

echo "Removing subversion internal tracking property\n";
passthru('svn pd svn:mergeinfo -R `pwd`');
?>
