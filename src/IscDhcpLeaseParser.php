<?php
/**
 *  This file is part of https://github.com/jpereira/php-dhcpdleases/
 *
 *  php-dhcpdleases is free software: you can redistribute it and/or modify it under the terms
 *  of the GNU Lesse General Public License as published by the Free Software Foundation, either
 *  version 3 of the License, or (at your option) any later version.
 *
 *  php-dhcpdleases is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 *  without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *  See the GNU Lesse General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesse General Public License
 *  along with php-dhcpdleases.
 *  If not, see <http://www.gnu.org/licenses/>.
 *
 *  Copyright (C) 2014, Jorge Pereira <jpereiran@gmail.com>
 */

/**
 * ISC DHCPD lease file parser
 */
class IscDhcpLeaseParser
{
    /**
     * ISC DHCP lease file path
     * @var string
     */
    var $file_path = "/var/lib/dhcpd/dhcpd.leases";

    /**
     * File pointer resource
     * @var mixed
     */
    var $resource = -1;

    /**
     * Leases
     * @var type
     */
    var $rows = array();

    /**
     * Loads the lease file
     * @param string $file_path
     */
    public function __construct($file_path = null)
    {
        // Overwrite default lease file given as an argument to the constructor
        if ($file_path != null) $this->file_path = $file_path;

        // Open given lease file
        if (!$this->resource = @fopen($this->file_path, 'r'))
        {
            throw new RuntimeException('Unable to open lease file: ' . $this->file_path);
        }
    }

    /**
     * Close the file upon instance destruction
     */
    public function __destruct()
    {
        if ($this->resource != null) fclose($this->resource);
    }

    /**
     * Process the lease file
     * returns total number of rows
     * @return integer
     */
    function process()
    {
        // Check if we have a valid file pointer resource
        if (!$this->resource) return false;

        // Loop thru the lease file until the end is reached
        while (!feof($this->resource))
        {
            $read_line = fgets($this->resource, 4096);

            if (substr($read_line, 0, 1) != "#")
            {
                $tok = strtok($read_line, " ");
                switch ($tok)
                {
                    case "lease":      // lease <ip> {
                        unset($arr);
                        $arr['ip'] = strtok(" ");
                        break;

                    case "starts":    // start
                        strtok(" ");
                        $arr['time-start'] = strtok(" ") . " " . strtok(";\n");
                        break;

                    case "ends":      // ends
                        strtok(" ");
                        $arr['time-end'] = strtok(" ") . " " . strtok(";\n");
                        break;

                    case "hardware":  // hardware
                        $field = strtok(" ");
                        if ($field == "ethernet")
                        {
                            $arr['hardware-ethernet'] = strtolower(strtok(";\n"));
                        }
                        break;

                    case "next":         // next binding state:
                        $tok = strtok(" ");
                        if ($tok == "binding")
                        {
                            $tok                       = strtok(" ");
                            if ($tok == "state")
                                    $arr['next-binding-state'] = strtok(";\n");
                        }
                        break;

                    case "binding":     // binding state:
                        $tok = strtok(" ");
                        if ($tok == "state")
                        {
                            $arr['binding-state'] = strtok(";\n");
                        }
                        break;

                    case "client-hostname":  // client-hostname
                        $arr['client-hostname'] = strtok("\";\n");
                        break;

                    case "uid":              // uid
                        $arr['uid'] = str_replace('"', "", strtok(";\n"));
                        break;

                    case "option":           // option { }
                        $tok = strtok(" ");
                        if ($tok == "agent.circuit-id")
                        {
                            $arr['circuit-id'] = preg_replace('/"(.*)"\n/', '${1}', strtok("\n"));
                            $arr['circuit-id'] = preg_replace('/(;$|\")', '', $arr['circuit-id']);
                        }

                        if ($tok == "agent.remote-id")
                        {
                            $arr['remote-id'] = preg_replace('/"(.*)";\n/', '${1}', strtok(" "));
                        }
                        break;

                    case "}\n":             // }
                        unset($arr);
                        break;
                }

                if (isset($arr['ip']) &&
                    isset($arr['time-start']) &&
                    isset($arr['time-end']) &&
                    isset($arr['hardware-ethernet']) &&
                    isset($arr['next-binding-state']) &&
                    isset($arr['binding-state']) &&
                    isset($arr['client-hostname'])
                )
                {
                    $this->rows[] = str_replace("\n", '', $arr);
                }
            }
        }

        return count($this->rows);
    }

    /**
     * Returns processed file as an array
     * @return array
     */
    function result()
    {
        return $this->rows;
    }
}