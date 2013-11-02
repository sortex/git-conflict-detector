<?php

class Git {

        public static $git_path = '/usr/bin/git';

        protected $_repo_path;

        public function __construct($repo_path)
        {
                $this->_repo_path = $repo_path;
        }

        public function execute($command)
        {
                $command = Git::$git_path.' '.$command;

                $descriptorspec = array(
                        1 => array('pipe', 'w'),
                        2 => array('pipe', 'w'),
                );

                $pipes = array();
                $resource = proc_open($command, $descriptorspec, $pipes, $this->_repo_path);

                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);

                foreach ($pipes as $pipe) {
                        fclose($pipe);
                }

                $status = trim(proc_close($resource));
                if ($status) throw new Exception($stderr);

                return $stdout;
        }

}
