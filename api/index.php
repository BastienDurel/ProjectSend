<?php
require 'vendor/autoload.php';
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include_once '../sys.includes.php';

$app = new \Slim\App;

// middleware: ensure there is a newline at the end of the output
class EnsureNl
{
    public function __invoke($request, $response, $next) {
        return $next($request, $response)->write("\n");
    }
}

// user viewable page
$app->get('/', 'showEndpoints');

// api
$app->get('/users', 'Users::get_list')->add(EnsureNl::class);
$app->get('/user/list', 'Users::get_list')->add(EnsureNl::class);
$app->get('/user/{id:[0-9]+}', 'Users::get')->add(EnsureNl::class);
$app->post('/user', 'Users::create')->add(EnsureNl::class);
$app->put('/user/{id:[0-9]+}', 'Users::update');
$app->delete('/user/{id:[0-9]+}', 'Users::del')->add(EnsureNl::class);

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
}

class Users {
    function get_list(Request $request, Response $response) {

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

        if ($id == 0) {
            return $response->withStatus(400)->write('You cannot delete your own account.');
        }

        // force auth... TODO: provides auth
        $_SESSION['userlevel'] = 9;
        
        $this_user = new UserActions();
        $delete_user = $this_user->delete_user($id);

        return $response->withHeader('Content-type', 'application/json')->write(json_encode($delete_user));
    }
}
