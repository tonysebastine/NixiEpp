<?php

/**
 * FOSSBilling Stub Classes for IDE Support
 * 
 * This file provides type hints for IDE autocompletion
 * Do NOT include this file in production - it's for development only
 * 
 * @package NixiEpp
 * @version 1.0.0
 */

// PSR-3 Logger Interface (must be first - dependency for Monolog)
namespace Psr\Log {
    /**
     * PSR-3 Logger Interface
     */
    interface LoggerInterface
    {
        public function emergency(string $message, array $context = []): void;
        public function alert(string $message, array $context = []): void;
        public function error(string $message, array $context = []): void;
        public function warning(string $message, array $context = []): void;
        public function notice(string $message, array $context = []): void;
        public function info(string $message, array $context = []): void;
        public function debug(string $message, array $context = []): void;
        public function log($level, string $message, array $context = []): void;
    }
}

namespace Box\Mod\Servicedomain\Registrar {
    /**
     * FOSSBilling Registrar Adapter Abstract
     * 
     * Base class for registrar modules in FOSSBilling
     */
    abstract class AdapterAbstract
    {
        /**
         * Configuration array
         */
        protected array $config = [];

        /**
         * Constructor
         */
        public function __construct(array $config)
        {
            $this->config = $config;
        }

        /**
         * Get logger instance
         * 
         * @return mixed
         */
        protected function getLog()
        {
            // Implemented by FOSSBilling
            return null;
        }

        /**
         * Get dependency injection container
         * 
         * @return mixed
         */
        protected function getDi()
        {
            // Implemented by FOSSBilling
            return null;
        }
    }
}

namespace {
    /**
     * TLD Model
     */
    class Model_Tld
    {
        public int $id = 0;
        public string $tld = '';
        public string $registrator = '';
        public string $price_registration = '0.00';
        public string $price_renew = '0.00';
        public string $price_transfer = '0.00';
        public int $min_years = 1;
        public ?string $updated_at = null;
        public ?string $created_at = null;
    }

    /**
     * FOSSBilling Dependency Injection Container
     */
    class DI
    {
        /**
         * Get dependency from container
         */
        public static function get(string $name = null): mixed
        {
            return null;
        }
    }
}

namespace Monolog {
    use \Psr\Log\LoggerInterface;

    /**
     * Monolog Logger (implements PSR-3)
     */
    class Logger implements LoggerInterface
    {
        const INFO = 200;
        const CRITICAL = 500;

        public function __construct(string $name) {}
        public function pushHandler($handler): void {}
        public function info(string $message, array $context = []): void {}
        public function crit(string $message, array $context = []): void {}
        public function emergency(string $message, array $context = []): void {}
        public function alert(string $message, array $context = []): void {}
        public function error(string $message, array $context = []): void {}
        public function warning(string $message, array $context = []): void {}
        public function notice(string $message, array $context = []): void {}
        public function debug(string $message, array $context = []): void {}
        public function log($level, string $message, array $context = []): void {}
    }
}

namespace Monolog\Handler {
    /**
     * Stream Handler
     */
    class StreamHandler
    {
        public function __construct($stream, $level = null) {}
    }
}
