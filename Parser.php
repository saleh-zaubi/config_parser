<?php

abstract class ConfigParser
{
    protected $file;
    protected $data;
    protected $matchCommentedConfig;

    function __construct($file)
    {
        $this->file = $file;
        $this->matchCommentedConfig = true;
    }

    protected function exists()
    {
        return file_exists($this->file);
    }

    public function allowCommentedConfig($allow)
    {
        $this->matchCommentedConfig = filter_var($allow, FILTER_VALIDATE_BOOLEAN);
    }

    public function print()
    {
        print_r($this->data);
    }

    public function dump()
    {
        var_dump($this->data);
    }

    abstract public function readFile();
}

class AsArrayParser extends ConfigParser
{
    function __construct($file)
    {
        parent::__construct($file);
        $this->data = [];
    }

    public function readFile()
    {
        if ($this->exists()) {
            $file_stream = fopen($this->file, "r");
            if ($file_stream) {
                while ($line = fgets($file_stream)) {
                    $this->handleLine($line);
                }
                fclose($file_stream);
            }
        } else {
            echo "The file doesn't exist";
        }
    }

    private function handleLine($line)
    {
        $line = $this->prepareLine($line);
        if ($line) {
            $variables = preg_replace("/\.+/", ".", trim($line[0]));
            $variables = preg_replace(["/^\./", "/\.$/"], "", $variables);
            $variables = explode(".", $variables);
            $value = $this->getValue(trim($line[1]));

            $this->addToData($this->data, $value, $variables);
        }
    }

    private function prepareLine($line)
    {
        if (strpos($line, "#") !== false) {
            if ($this->matchCommentedConfig) {
                preg_match("/[a-z\.]+\s*=\s*[a-z0-9]+/i", $line, $matches);
                if ($matches) {
                    $line = $matches[0];
                }
            } else {
                $line = substr_replace($line, "", strpos($line, "#"));
            }
        }

        if (strpos($line, "=") !== false) {
            $line = explode("=", $line);
        } else {
            $line = [];
        }

        return $line;
    }

    private function getValue($value)
    {
        if (is_numeric($value)) {
            return intval($value);
        }

        $booleans = ["true", "false", "yes", "no", "on", "off"];
        if (in_array(strtolower($value), $booleans)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $str_len = strlen($value);
        if ($str_len > 1) {
            $quotations = ["\"", "'"];
            if (in_array($value[0], $quotations) && in_array($value[$str_len-1], $quotations)) {
                return substr($value, 1, $str_len-2);
            }
        }

        return $value;
    }

    private function addToData(&$data, $value, $variables, $var_index = 0)
    {
        if (count($variables) == $var_index+1) {
            $data[$variables[$var_index]] = $value;
        } elseif (array_key_exists($variables[$var_index], $data)) {
            $this->addToData($data[$variables[$var_index]], $value, $variables, $var_index+1);
        } else {
            $data[$variables[$var_index]] = [];
            $this->addToData($data[$variables[$var_index]], $value, $variables, $var_index+1);
        }
    }
}
