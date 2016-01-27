<?php

include_once 'epiphany/Epi.php';

Epi::setPath('base', 'epiphany');
Epi::init('api');

// user viewable page
getRoute()->get('/', 'showEndpoints');

// api
// TODO


getRoute()->run();

function showEndpoints()
{
      echo '<ul>
          <li><a href="/api">/</a> -> (home)</li>
          <!-- TODO -->
        </ul>';
}

