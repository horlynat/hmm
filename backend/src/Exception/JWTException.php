<?php
// src/Exception/JWTException.php
namespace App\Exception;

use Exception;

class JWTException extends Exception {}

class JWTExpiredException extends JWTException {}

class JWTInvalidSignatureException extends JWTException {}

class JWTInvalidFormatException extends JWTException {}