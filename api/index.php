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
getApi()->put('user', array('Users', 'create'), EpiApi::external);
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

function get_data() {
    switch ($_SERVER['CONTENT_TYPE']) {
    case 'application/x-www-form-urlencoded':
        parse_str(file_get_contents("php://input"), $vars);
        break;
    default:
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Unknown content-type';
        exit();
    }
    return $vars;
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

    function create() {
        $vars = get_data();
        error_log(print_r($vars, true));

        global $database;
        $database->MySQLDB();

        // cleanup
        $add_user_data_name = encode_html($vars['name']);
        $add_user_data_email = encode_html($vars['email']);
        $add_user_data_level = encode_html($vars['level']);
        $add_user_data_user = encode_html($vars['user']);
        $add_user_data_active = (isset($vars["active"])) ? 1 : 0;


        $new_user = new UserActions();
        $new_arguments = array(
            'id' => '',
            'username' => $add_user_data_user,
            'password' => $vars['pass'],
            'name' => $add_user_data_name,
            'email' => $add_user_data_email,
            'role' => $add_user_data_level,
            'active' => $add_user_data_active,
            'type' => 'new_user'
        );

        /** Validate the information from the posted data. */
        $new_validate = $new_user->validate_user($new_arguments);

        /** Create the user if validation is correct. */
        if ($new_validate == 1) {
            $new_response = $new_user->create_user($new_arguments);
            return $new_response;
        }
        else {
            header('HTTP/1.1 400 Bad Request');
            global $valid_me;
            return $valid_me->error_msg;
        }
    }
}
