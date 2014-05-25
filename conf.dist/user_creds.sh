#!/bin/bash
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
if [ -z "$SVN_USERNAME" ]
then
    SVN_USERNAME=
fi

if [ -z "${SVN_PASSWORD}" ]
then
    SVN_PASSWORD=
fi

NEXT_IS_USERNAME="false"
NEXT_IS_PASSWORD="false"
for arg in $*
do
    if [ "${NEXT_IS_USERNAME}" = "true" ]
    then
        SVN_USERNAME="${arg}"
        NEXT_IS_USERNAME=false
    elif [ "${NEXT_IS_PASSWORD}" = "true" ]
    then
        SVN_PASSWORD="${arg}"
        NEXT_IS_PASSWORD=false
    else
        if [ "${arg:0:10}" = '--username' ]
        then
            if [ "${arg:10:1}" = "=" ]
            then
                SVN_USERNAME="${arg:11}"
            else
                NEXT_IS_USERNAME="true"
            fi
        elif [ "${arg:0:10}" = '--password' ]
        then
            if [ "${arg:10:1}" = "=" ]
            then
                SVN_PASSWORD="${arg:11}"
            else
                NEXT_IS_PASSWORD="true"
            fi
        fi
    fi
done

while [ -z "$SVN_USERNAME" ]
do
    echo -n "Please enter your subversion username: "
    read SVN_USERNAME
done

while [ -z "$SVN_PASSWORD" ]
do
    echo -n "Please enter your subversion password: "
    oldmodes=`stty -g`
    stty -echo
    read SVN_PASSWORD
    stty $oldmodes
    echo
done
