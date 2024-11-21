<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;  // This line is needed to import the base controller

class ControllerTP extends Controller
{
    public function index()
    {
        return view('TP.index');
    }
}
