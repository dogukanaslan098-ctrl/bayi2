<?php // views/pages/logout.php
use Auth\Auth;
Auth::logout();
redirect('/login');
