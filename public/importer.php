<?php
/*
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2015 Kim Henriksen
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * ISC DHCPD CLIENT LEASE IMPORTER
 *
 * For use on eg. Pfense
 *
 * Required Dependencies:
 *              php-mysql (pfense: https://doc.pfsense.org/index.php/How_do_I_get_PHP_support_for_mysql,_sqlite,_sockets,_etc)
 *
 * ISC DHCP lease parser library based upon: https://github.com/jpereira/php-dhcpdleases
 *
 * DATABASE AND TABLE STRUCTURE
 * ---------------------------------------------------------------
 * CREATE DATABASE IF NOT EXISTS `dhcp` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
 * USE `dhcp`;
 *
 * CREATE TABLE IF NOT EXISTS `leases` (
 * `id` int(11) NOT NULL,
 *   `ip` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
 *   `time_start` datetime DEFAULT NULL,
 *   `time_end` datetime DEFAULT NULL,
 *   `binding_state` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
 *   `next_binding_state` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
 *   `hardware_ethernet` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
 *   `client_hostname` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
 * ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
 *
 *
 * ALTER TABLE `leases`
 *  ADD PRIMARY KEY (`id`);
 *
 *
 * ALTER TABLE `leases`
 * MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
 * ---------------------------------------------------------------
 */

/* ----------------- CONFIG BEGIN ----------------- */

/*
 * ---------------------------------------------------------------
 * MYSQL CONNECTION SETTINGS
 * ---------------------------------------------------------------
 *
 * Username, password, host and database name
 */
define('MYSQL_HOSTNAME', '192.168.1.5');
define('MYSQL_USERNAME', 'pfsense');
define('MYSQL_PASSWORD', 'V3LBdnaae68jdrsE');
define('MYSQL_DATABASE', 'dhcp');
define('MYSQL_TABLE', 'leases');

/*
 * ---------------------------------------------------------------
 * ISC DHCP LEASE FILE
 * ---------------------------------------------------------------
 *
 * Path wh
 */
define('SRC_PATH', realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'src') . DIRECTORY_SEPARATOR);

/*
 * ---------------------------------------------------------------
 * ISC DHCP LEASE FILE
 * ---------------------------------------------------------------
 *
 * Path of the ISC DHCP lease file
 */
//define('LEASE_FILE_PATH', realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'dhcpd.leases'));
define('LEASE_FILE_PATH', '/var/dhcpd/var/db/dhcpd.leases');

/*
 * ---------------------------------------------------------------
 * ERROR REPORTING
 * ---------------------------------------------------------------
 *
 * Change display_errors to 0 when running this script live to hide errors
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ----------------- CONFIG END ----------------- */

// Load dhcp lease parser library
require SRC_PATH . 'IscDhcpLeaseParser.php';

// Create connection
$mysqli = new mysqli(MYSQL_HOSTNAME, MYSQL_USERNAME, MYSQL_PASSWORD, MYSQL_DATABASE);

// Check connection
if ($mysqli->connect_error)
{
    throw new RuntimeException("Connection failed: " . $mysqli->connect_error);
}

// Instantiate ISC DHCP lease file parser library
$leases = new IscDhcpLeaseParser(LEASE_FILE_PATH);

// Process ISC DHCP file
if (!$leases->process())
{
    throw new RuntimeException("Failed to parse dhcpd lease database");
}

// Clear current data
if (!$mysqli->query('TRUNCATE TABLE `leases`;'))
{
    throw new RuntimeException("Query failed: " . $mysqli->error);
}

// Loop each lease
foreach ($leases->result() as $lease)
{
    // Insert each lease into lease table
    if (!$mysqli->query("INSERT INTO `" . MYSQL_TABLE . "` (ip, time_start, time_end, binding_state, next_binding_state, hardware_ethernet, client_hostname) VALUES ('" . $lease['ip'] . "', '" . date('Y-m-d H:i:s', strtotime($lease['time-start'])) . "', '" . date('Y-m-d H:i:s', strtotime($lease['time-end'])) . "', '" . $lease['binding-state'] . "', '" . $lease['next-binding-state'] . "', '" . $lease['hardware-ethernet'] . "', '" . $lease['client-hostname'] . "')"))
    {
        throw new RuntimeException("Query failed: " . $mysqli->error);
    }
}