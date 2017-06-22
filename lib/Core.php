<?php

class Core {

  static function autoloadLibClass($className) {
    $filename = __DIR__ . '/' . $className . '.php';
    if (file_exists($filename)) {
      require_once $filename;
    }
  }

  static function init() {
    mb_internal_encoding('UTF-8');

    spl_autoload_register(); // clear the autoload stack
    spl_autoload_register('Core::autoloadLibClass', false, true);
  }
}

Core::init();
