<?php

include_once 'epiphany/Epi.php';
include_once '../sys.includes.php';

Epi::setPath('base', 'epiphany');
Epi::init('api');

// user viewable page
getRoute()->get('/', 'showEndpoints');

// api
getApi()->get('users', array('Users', 'get_list'), EpiApi::external);
getApi()->get('user/list', array('Users', 'get_list'), EpiApi::external);
getApi()->get('user/(\d+)', array('Users', 'get'), EpiApi::external);
// TODO

getRoute()->run();

function showEndpoints()
{
      echo '<ul>
          <li><a href="/api">/</a> -> (home)</li>
          <li>/users -> Get user list</li>
          <li>/user/list -> Get user list</li>
          <li>/user/(id) -> Get user by id</li>
          <!-- TODO -->
        </ul>';
}

class Users {
    function get_list() {

        global $database;

        $database->MySQLDB();
        $cq = "SELECT * FROM tbl_users WHERE level != '0'";

        $ret = array();

        $cq .= " ORDER BY name ASC";

        $sql = $database->query($cq);
        $count = mysql_num_rows($sql);

        while($row = mysql_fetch_array($sql)) {
            $u = array();
            $u['id'] = $row['id'];
            $u['user'] = $row['user'];
            $ret[] = $u;
        }

        $database->Close();
        
        return $ret;
    }

    function get($id) {

        global $database;

        $database->MySQLDB();
        $cq = "SELECT * FROM tbl_users WHERE id = " . ((int)$id);
        $sql = $database->query($cq);
        if ($row = mysql_fetch_array($sql)) {
            $u = array();
            $u["id"] = $row["id"];
            $u["user"] = $row["user"];
            $u["name"] = $row["name"];
            $u["email"] = $row["email"];
            $u["level"] = $row["level"];
            $u["timestamp"] = $row["timestamp"];
            $u["address"] = $row["address"];
            $u["phone"] = $row["phone"];
            $u["notify"] = $row["notify"];
            $u["contact"] = $row["contact"];
            $u["created_by"] = $row["created_by"];
            $u["active"] = $row["active"];
            return $u;
        }
        else
            return array();
        
    }
}
