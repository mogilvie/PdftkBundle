<?php

/**
 * Description of PdftkManager
 *
 * @author VHoude
 */

namespace SpecShaper\PdftkBundle\Managers;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PdftkManager
{
    const FILE_PREFIX = 'pdftk_';

    private $fdf_signature = array(
        'arr_strings' => array(),
        'arr_booleans' => array(),
        'arr_hidden' => array(),
        'arr_readonly' => array(),
    );

    private $pdftkCmds = array(
        'fill_form' => 'pdftk %s fill_form - output -',
        'generate_fdf' => 'pdftk %s generate_fdf output -',
        'dump_data_fields' => 'pdftk %s dump_data_fields output -',
    );

    private $pdftkPdfBooleanYes;
    private $temporaryFolder;

    public function __construct($rootDir, $pdftk_parameters = array())
    {
        $this->pdftkCmds = \array_intersect_key(
            (\array_key_exists('cmds', $pdftk_parameters)
                ?$pdftk_parameters['cmds']
                :array())
            , $this->pdftkCmds
        );

        $this->pdftkCmds['fill_form'] = (\array_key_exists('fill_form', $this->pdftkCmds)
            ?$this->pdftkCmds['fill_form']
            :'pdftk %s fill_form - output -');

        $this->pdftkCmds['generate_fdf'] = (\array_key_exists('generate_fdf', $this->pdftkCmds)
            ?$this->pdftkCmds['generate_fdf']
            :'pdftk %s generate_fdf output -');

        $this->pdftkCmds['dump_data_fields'] = (\array_key_exists('dump_data_fields', $this->pdftkCmds)
            ?$this->pdftkCmds['dump_data_fields']
            :'pdftk %s dump_data_fields output -');

        $this->pdftkPdfBooleanYes = (\array_key_exists('pdf_boolean_yes', $pdftk_parameters)
            ?$pdftk_parameters['pdf_boolean_yes']
            :'Yes');

        $this->temporaryFolder = \realpath(\sprintf('%s', $rootDir))
            . DIRECTORY_SEPARATOR . 'pdftk';

        if (\file_exists($this->temporaryFolder) === false)
            if (\mkdir($this->temporaryFolder) === false)
                throw new \Exception('Failed to create temp dir: '.$this->temporaryFolder);
    }

    public function getParamPdfBooleanYes($url = null)
    {
        if (!$url)
        {
            return $this->pdftkPdfBooleanYes;
        }
        $fields = $this->dump_data_fields($url);
        $radio_options = array();
        $wording = 'FieldStateOption:';
        $pattern = \sprintf("/\b%s \w+\b/i", $wording);
        $radio_fields = \preg_grep($pattern, \file($fields));
        // we parse first 2 cases mentionning either possibility for a checkbox
        foreach($radio_fields as $option)// the first one is Off and the next Yes,
        {// actually Off is unchecked no matter the position and the other is checked: Yes Oui On 1
            $sanitized_option = \trim(\str_replace($wording, '', \trim($option)));
            if (\in_array($sanitized_option, array("0", "1",))) // haystack is full of strings !
            {
                $sanitized_option = (bool)$sanitized_option;
            }
            // !! in the loop, if the Off is not set then it is the Yes :)
            if (empty($radio_options))
            {
                $radio_options [($sanitized_option!=='Off'?'Yes':'Off')]= $sanitized_option;
            } else {
                $radio_options [(!isset($radio_options['Off'])?'Off':'Yes')]= $sanitized_option;
                break;
            }
        }
        if (isset($radio_options['Yes']))
        {
            $this->pdftkPdfBooleanYes = $radio_options['Yes'];
        }

        return $this->pdftkPdfBooleanYes;
    }

    public function save($url, $data, $keep_fdf = false, $hexify_kept_fdf = true)
    {
        $fdf_data = $this->fdf_signature;
        foreach($fdf_data as $k => $v)
            $fdf_data[$k] = (\array_key_exists($k, $data)?$data[$k]:$v);

        $output_tmp = $this->temporaryFolder
            . DIRECTORY_SEPARATOR . self::FILE_PREFIX . \time() . '_' . \basename($url);

        $cmd = $this->pdftkCmds['fill_form'];

        $stdin = array(
            'callback' => array($this, '_forge_fdf_utf8',),
            'args' => array($fdf_data, $hexify_kept_fdf,),
        );
        $stdout = array(
            'ext' => '.pdf',
            'prefix' => '_pdf',
        );

        $output = $this->_generic_pipe_process($cmd, $url, $output_tmp, $stdout, $stdin);

        if ($keep_fdf)
            $this->fetch_fdf($url);

        return $output;
    }

    public function fetch_fdf($url)
    {
        $output = $this->temporaryFolder
            . DIRECTORY_SEPARATOR . self::FILE_PREFIX . \time() . '_' . \basename($url);
        $cmd = $this->pdftkCmds['generate_fdf'];
        $stdout = array(
            'ext' => '.fdf',
            'prefix' => '_fdf',
        );

        $fdf = $this->_generic_pipe_process($cmd, $url, $output, $stdout);

        return $fdf;
    }

    /**
     * @todo decompose this output to work it out
     * @see getParamPdfBooleanYes for other cases in the same way as radio
     * @param type $url
     * @return type
     */
    public function dump_data_fields($url)
    {
        $output = $this->temporaryFolder
            . DIRECTORY_SEPARATOR . self::FILE_PREFIX . \time() . '_' . \basename($url);
        $cmd = $this->pdftkCmds['dump_data_fields'];
        $stdout = array(
            'ext' => '.txt',
            'prefix' => '_txt',
        );

        $data_fields = $this->_generic_pipe_process($cmd, $url, $output, $stdout);

        return $data_fields;
    }

    public function clear($path)
    {
        if (\file_exists($path))
        {
            $info = \pathinfo($path);
            if ((\strstr($info['basename'], self::FILE_PREFIX) !== false) && ($info['dirname'] == $this->temporaryFolder))
                \unlink($path);
        }
    }

    public function encrypt($path, $ownerPw, $userPw = null, $isPrintable = false)
    {

       // pdftk 1.pdf output 1.128.pdf owner_pw foo user_pw baz allow printing
        $commandline = 'pdftk "' . $path .'.pdf" output "'  . $path .'.128.pdf" owner_pw ' . $ownerPw;

        if($userPw !== null){
            $commandline .= ' user_pw ' . $userPw;
        }

        if($isPrintable){
            $commandline .= ' allow printing';
        }

        $process = new Process($commandline);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        echo $process->getOutput();

    }

    ##################

    private function _generic_pipe_process($cmd, $input_url, $output_tmp, $stdout = array(), $stdin = array())
    {
        $expected_stdin = array('callback' => null,'args' => array(),);
        $_stdin = \array_intersect_key($stdin, $expected_stdin);

        $expected_stdout = array('ext' => '.out','prefix' => '_gp',);
        $_stdout = \array_intersect_key($stdout, $expected_stdout);

        $output = $output_tmp . $_stdout['ext'];
        $output_log = $output_tmp . $_stdout['prefix'] . '.log';

        $_command = \sprintf($cmd, $input_url);

        $descriptorspec = array();
        if (\array_key_exists('callback', $_stdin))
           $descriptorspec [0]= array('pipe', 'r'); // stdin
        $descriptorspec [1]= array('pipe', 'w'); // stdout
        $descriptorspec [2]= array('file', $output_log, 'w'); // stderr

        $process = \proc_open($_command, $descriptorspec, $pipes);

        if (\is_resource($process))
        {
            if (isset($descriptorspec[0]))
            {
                \fwrite($pipes[0], \call_user_func_array($_stdin['callback'], $_stdin['args']));
                \fclose($pipes[0]);
            }
            if (isset($descriptorspec[1]))
            {
                $content = \stream_get_contents($pipes[1]);
                \file_put_contents($output, $content);
                \fclose($pipes[1]);
                \proc_close($process);
            }
        }

        if (\file_exists($output_log))
            if (\filesize($output_log) === 0)
                \unlink($output_log);
            else {
                \error_log('Error: '.\file_get_contents($output_log));
                return '1>&2';
            }

        return $output;
    }

    /**
     * Generate the fdf code
     *
     * @param Array $fdf_data : expects an array with fields serving each purpose to populate
     * @param Boolean $hexify : whether encoding is needed or not
     *
     * @return String
     */
    private function _forge_fdf_utf8($fdf_data, $hexify = true)
    {
        $arr_strings = $arr_booleans = $arr_hidden = $arr_readonly = array();
        \extract($fdf_data);// will overwrite arr_* predefined above

        $fdf = "%FDF-1.2\x0d%\xe2\xe3\xcf\xd3\x0d\x0a"; // header
        $fdf .= "1 0 obj\x0d << "; // open the Root dictionary
        $fdf .= "\x0d/FDF << "; // open the FDF dictionary
        $fdf .= "/Fields [ "; // open the form Fields array

        // string data, used for text fields, combo boxes and list boxes
        $fdf .= $this->_parseSBArrays($arr_strings, $arr_hidden, $arr_readonly, $hexify);

        // name data, used for checkboxes and radio buttons
        // (e.g., /Yes or /Oui depending on locale for true and /Off for false)
        $fdf .= $this->_parseSBArrays($arr_booleans, $arr_hidden, $arr_readonly, $hexify);

        $fdf .= "] \x0d"; // close the Fields array

        $fdf .= ">> \x0d"; // close the FDF dictionary
        $fdf .= ">> \x0dendobj\x0d"; // close the Root dictionary
        // trailer; note the "1 0 R" reference to "1 0 obj" above
        $fdf .= "trailer\x0d<<\x0d/Root 1 0 R \x0d\x0d>>\x0d";
        $fdf .= "%%EOF\x0d\x0a";

        return $fdf;
    }

    /**
     * Parse strings and booleans to yield proper FDF formatted output
     * @param type $arr_strings_or_booleans
     * @param type $arr_hidden
     * @param type $arr_readonly
     * @return string
     */
    private function _parseSBArrays($arr_strings_or_booleans, $arr_hidden, $arr_readonly, $hexify = true)
    {
        $fdf = '';
        foreach ($arr_strings_or_booleans as $key => $value)
        {
            $fdf .= "<< /V " . ($hexify?$this->_hexify_utf8($value):\addslashes($value))
                . " /T " . ($hexify?$this->_hexify_utf8($key):\addslashes($key))
                . " ";
            if (\in_array($key, $arr_hidden))
                $fdf .= "/SetF 2 ";
            else
                $fdf .= "/ClrF 2 ";

            if (\in_array($key, $arr_readonly))
                $fdf .= "/SetFf 1 ";
            else
                $fdf .= "/ClrFf 1 ";

            $fdf .= ">> \x0d";
        }

        return $fdf;

    }

    /**
     * Convert a utf8 string to its hex represented value encapsulated by <> so it is displayed properly in PDF
     * @param type $string
     * @return string
     */
    private function _hexify_utf8($string)
    {
        $processed_value = \utf8_decode($string);
        $processed_value = '<'.\bin2hex($processed_value).'>';// if represented as hex, then encapsulate in <>

        return $processed_value;
    }

}
