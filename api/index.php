<?php
require 'vendor/autoload.php';
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

// Do not use auto session
$API_NO_SESSION = true;
include_once '../sys.includes.php';
if (ini_get("session.use_cookies"))
    ini_set("session.use_cookies", 0);

$app = new \Slim\App;

// middleware: ensure there is a newline at the end of the output
class EnsureNl
{
    public function __invoke($request, $response, $next) {
        return $next($request, $response)->write("\n");
    }
}

class CheckLogin
{
    public function __invoke($request, $response, $next) {
        $_session = $request->getHeader('X-Session');
        if (!empty($_session)) {
            if (is_array($_session)) {
                if (count($_session))
                    $session = array_pop($_session);
            }
            else
                $session = $_session;
        }
        else {
            switch ($request->getMethod()) {
            case 'POST':
            case 'PUT':
                $vars = $request->getParsedBody();
                if (array_key_exists('session', $vars)) {
                    $session = $vars['session'];
                    break;
                }
                // else do not break and take it from query
            case 'GET':
            case 'DELETE':
            default:
                $vars = $request->getQueryParams();
                if (array_key_exists('session', $vars))
                    $session = $vars['session'];
                break;
            }
        }
        if (isset($session)) {
            session_id($session);
            session_start();
            if (isset($_SESSION['userlevel']))
                return $next($request, $response);
            else
                $response->write("Cannot find userlevel into session\n");
        }
        return $response->withStatus(403)->write("No authentication, or authentication failed\n");
    }

    public static function checkLevel($levels) {
        if (!is_array($levels))
            $levels = array($levels);
        return in_array($_SESSION['userlevel'], $levels);
    }
}

// user viewable page
$app->get('/', 'showEndpoints');

// api
$app->get('/users', 'Users::get_list')->add(EnsureNl::class)->add(CheckLogin::class);
$app->group('/user', function () use ($app) {
    $app->get('/list', 'Users::get_list')->add(EnsureNl::class);
    $app->get('/{id:[0-9]+}', 'Users::get')->add(EnsureNl::class);
    $app->post('/', 'Users::create')->add(EnsureNl::class);
    $app->put('/{id:[0-9]+}', 'Users::update');
    $app->delete('/{id:[0-9]+}', 'Users::del')->add(EnsureNl::class);
})->add(CheckLogin::class);
$app->group('/group', function () use ($app) {
    $app->get('/list', 'Groups::get_list')->add(EnsureNl::class);
    $app->get('/{id:[0-9]+}', 'Groups::get')->add(EnsureNl::class);
    $app->post('/', 'Groups::create')->add(EnsureNl::class);
    $app->delete('/{id:[0-9]+}', 'Groups::del')->add(EnsureNl::class);
})->add(CheckLogin::class);
$app->post('/login', 'Api::login')->add(EnsureNl::class);
$app->post('/logout', 'Api::logout')->add(EnsureNl::class)->add(CheckLogin::class);

$app->run();

function showEndpoints(Request $request, Response $response)
{
    $response->write('<ul>
          <li><a href="/api">/</a> -> (home)</li>
          <li>GET /users -> Get user list</li>
          <li>PUT /user -> Create user</li>
          <li>GET /user/list -> Get user list</li>
          <li>GET /user/(id) -> Get user by id</li>
          <li>DELETE /user/(id) -> Delete user by id</li>
          <!-- TODO -->
        </ul>');
    return $response;
}

class Api {
    function login(Request $request, Response $response)
    {
        global $database;
        global $hasher;
        $vars = $request->getParsedBody();

        if (!array_key_exists('user', $vars) || !array_key_exists('password', $vars))
            return $response->withStatus(400)->write('Missing username or password');

        $database->MySQLDB();
        $sysuser_username = mysql_real_escape_string($vars['user']);
        $sysuser_password = mysql_real_escape_string($vars['password']);

        $sql_user = $database->query("SELECT * FROM tbl_users WHERE BINARY user='$sysuser_username'");
        $count_user = mysql_num_rows($sql_user);
        if ($count_user < 1)
            return $response->withStatus(403)->write('Bad username or password');
        while ($row = mysql_fetch_array($sql_user)) {
            $db_pass = $row['password'];
            $user_level = $row["level"];
            $active_status = $row['active'];
            $logged_id = $row['id'];
            $global_name = $row['name'];
        }
        $check_password = $hasher->CheckPassword($sysuser_password, $db_pass);
        if (!$check_password)
            return $response->withStatus(403)->write('Bad username or password');

        if ($active_status == '0')
            return $response->withStatus(403)->write('Account disabled');

        if ($user_level == '0')
            return $response->withStatus(403)->write('Access restricted to admin only');

        session_start();
        $_SESSION['loggedin'] = $sysuser_username;
        $_SESSION['userlevel'] = $user_level;
        $_SESSION['access'] = 'admin';
        $_SESSION['logged_id'] = (int)$logged_id;


        /** Record the action log */
        $new_log_action = new LogActions();
        $log_action_args = array(
            'action' => 1,// TODO: maybe defines a "login with API" action ?
            'owner_id' => $logged_id,
            'affected_account_name' => $global_name
        );
        $new_record_action = $new_log_action->log_action_save($log_action_args);

        $ret = array('session' => session_id());
        return $response->withHeader('Content-type', 'application/json')->write(json_encode($ret));
    }

    function logout(Request $request, Response $response) {
        session_destroy();
        return $response;
    }
}

class Users {
    function get_list(Request $request, Response $response) {

        if (!CheckLogin::checkLevel(array(9)))
            return $response->withStatus(403)->write('Not enought rights');

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

        return $response->withHeader('Content-type', 'application/json')->write(json_encode($ret));
    }

    function get(Request $request, Response $response, $args) {

        if (!CheckLogin::checkLevel(array(9)))
            return $response->withStatus(403)->write('Not enought rights');

        global $database;

        $id = (int)$args['id'];

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

            return $response->withHeader('Content-type', 'application/json')->write(json_encode($u));
        }
        else
            return $response->withHeader('Content-type', 'application/json')->write(json_encode(null));
        
    }

    // use POST for create, then we can return new user's data
    function create(Request $request, Response $response) {
        $vars = $request->getParsedBody();

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

            return $response->withHeader('Content-type', 'application/json')->write(json_encode($new_response));
        }
        else {
            global $valid_me;
            return $response->withStatus(400)->write($valid_me->error_msg);
        }
    }

    // Use PUT for update as we don't need to return anything
    function update(Request $request, Response $response, $args) {
        global $database;

        $id = (int)$args['id'];
        
        if (user_exists_id($id)) {

            $database->MySQLDB();
            $editing = $database->query("SELECT * FROM tbl_users WHERE id=$id");

            if ($edit_arguments = mysql_fetch_array($editing)) {

                for ($i = 0; $i < 15; ++$i)
                    if (array_key_exists($i, $edit_arguments))
                        unset($edit_arguments[$i]);
                    else
                        break;

                $data = $request->getParsedBody();
                
                if (isset($data['name'])) $edit_arguments['name'] = mysql_real_escape_string($data['name']);
                if (isset($data['user'])) $edit_arguments['user'] = mysql_real_escape_string($data['user']);
                if (isset($data['email'])) $edit_arguments['email'] = mysql_real_escape_string($data['email']);

                if (isset($data['phone'])) $edit_arguments['phone'] = mysql_real_escape_string($data['phone']);

                // special case for password:
                $edit_arguments['password'] = isset($data['password']) ? $data['password'] : '';
                // special case for level:
                $edit_arguments['role'] = isset($data['level']) ? $data['level'] : $edit_arguments['level'];
                unset($edit_arguments['level']);
            }
            else {
                return $response->withStatus(500)->write("Got no user data");
            }
            
            /** Create the object */
            $edit_user = new UserActions();

            /** Validate the information. */
            $edit_validate = $edit_user->validate_user($edit_arguments);

            /** Create the user if validation is correct. */
            if ($edit_validate == 1) {
                $edit_response = $edit_user->edit_user($edit_arguments);
            }
            else {
                global $valid_me;
                return $response->withStatus(400)->write($valid_me->error_msg);
            }
            
            return $response;
        }
        else {
            return $response->withStatus(400)->write('Non-existant user');
        }
    }
    
    function del(Request $request, Response $response, $args) {

        $id = (int)$args['id'];

        if ($id == $_SESSION['logged_id']) {
            return $response->withStatus(400)->write('You cannot delete your own account.');
        }
        
        $this_user = new UserActions();
        $delete_user = $this_user->delete_user($id);

        return $response->withHeader('Content-type', 'application/json')->write(json_encode($delete_user));
    }
}

class Groups {
    function get_list(Request $request, Response $response) {

        if (!CheckLogin::checkLevel(array(9, 8)))
            return $response->withStatus(403)->write('Not enought rights');

        global $database;

        $database->MySQLDB();
        $cq = "SELECT id,name FROM tbl_groups";

        $ret = array();

        $cq .= " ORDER BY id ASC";

        $sql = $database->query($cq);
        $count = mysql_num_rows($sql);

        while($row = mysql_fetch_array($sql)) {
            $u = array();
            $u['id'] = $row['id'];
            $u['name'] = $row['name'];
            $ret[] = $u;
        }

        $database->Close();

        return $response->withHeader('Content-type', 'application/json')->write(json_encode($ret));
    }

    function _get($id) {
        global $database;
        
        $fcount = "SELECT COUNT(file_id) files FROM tbl_files_relations WHERE group_id = " . ((int)$id);
        $ucount = "SELECT COUNT(client_id) members_count FROM tbl_members WHERE group_id = " . ((int)$id);

        $database->MySQLDB();
        $cq = "SELECT * FROM tbl_groups g, ($fcount) f, ($ucount) u WHERE g.id = " . ((int)$id);
        $sql = $database->query($cq);
        if ($row = mysql_fetch_array($sql)) {
            for ($i = 0; $i < 15; $i++)
                if (array_key_exists($i, $row))
                    unset($row[$i]);

            $row['members'] = array();

            $uq = "SELECT u.id, u.name FROM tbl_members m, tbl_users u WHERE m.client_id = u.id AND m.group_id = " . ((int)$id);
            $sqlu = $database->query($uq);
            while($rowu = mysql_fetch_array($sqlu)) {
                for ($i = 0; $i < 2; $i++)
                    if (array_key_exists($i, $rowu))
                        unset($rowu[$i]);
                $row['members'][] = $rowu;
            }

            return $row;
        }
        else
            return null;
    }

    function get(Request $request, Response $response, $args) {

        if (!CheckLogin::checkLevel(array(9, 8)))
            return $response->withStatus(403)->write('Not enought rights');

        $id = (int)$args['id'];

        $group = self::_get($id);

        return $response->withHeader('Content-type', 'application/json')->write(json_encode($group));
    }

    function create(Request $request, Response $response) {
        $vars = $request->getParsedBody();

        global $database;
        $database->MySQLDB();

        // cleanup
        $add_group_data_name = encode_html($vars['name']);
        $add_group_data_description = isset($vars['description']) ? encode_html($vars['description']) : '';

        $new_group = new GroupActions();
        $new_arguments = array(
            'id' => '',
            'name' => $add_group_data_name,
            'description' => $add_group_data_description,
            'role' => $vars['members']
        );

        /** Validate the information from the posted data. */
        $new_validate = $new_group->validate_group($new_arguments);

        /** Create the user if validation is correct. */
        if ($new_validate == 1) {
            $new_response = $new_group->create_group($new_arguments);

            return $response->withHeader('Content-type', 'application/json')->write(json_encode($new_response));
        }
        else {
            global $valid_me;
            return $response->withStatus(400)->write($valid_me->error_msg);
        }
    }

    function del(Request $request, Response $response, $args) {

        $id = (int)$args['id'];

        $group = self::_get($id);
        if ($group == null)
            return $response->withStatus(400)->write('Unknown group');

        $this_group = new GroupActions();
        $delete_group = $this_group->delete_group($id);

        /** Record the action log */
        $new_log_action = new LogActions();
        $log_action_args = array(
            'action' => 18,
            'owner_id' => $_SESSION['logged_id'],
            'affected_account_name' => $group['name']
        );
        $new_record_action = $new_log_action->log_action_save($log_action_args);

        return $response->withHeader('Content-type', 'application/json')->write(json_encode($delete_group));
    }
}