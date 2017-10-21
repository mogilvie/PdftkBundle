<?php

namespace SpecShaper\PdftkBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{

    /**
     * @dataProvider getPdfFormData
     */
    public function testPdftk($arr_values)
    {
        \error_log(PHP_EOL."testPdftk ### " . __FILE__ . "@" . __LINE__ . "###");
        $client = static::createClient();

        $pdftkManager = $client->getContainer()->get('pdftk.managers.pdftk', array());

        $arr_strings = $arr_booleans = $arr_hidden = $arr_readonly = array();

        $pdf_form = 'pdf_form_full';

        $input = \dirname(\dirname(__DIR__)) . DIRECTORY_SEPARATOR
            . 'Resources' . DIRECTORY_SEPARATOR . 'doc' . DIRECTORY_SEPARATOR . $pdf_form . '.pdf';

        $pdf_boolean_yes = $pdftkManager->getParamPdfBooleanYes($input);
        foreach($arr_values as $k => $v)
        {
            if (\stristr($k, 'address'))
                $v = $v;
            if (\stristr(\str_replace(' ', '', $k), 'checkbox'))
                $arr_booleans [$k]= $pdf_boolean_yes; // check all boxes while testing !
            else
                $arr_strings [$k]= $v;
        }

        $data['arr_strings'] = $arr_strings;
        $data['arr_booleans'] = $arr_booleans;

        $output_fields = $pdftkManager->dump_data_fields($input);// create txt with fields
        $this->assertTrue(\file_exists($output_fields));

        $this->assertTrue(($pdf_boolean_yes==='Yes'));

        $output_fdf = $pdftkManager->fetch_fdf($input);// create fdf
        $this->assertTrue(\file_exists($output_fdf));

        $output = $pdftkManager->save($input, $data);// create pdf
        $this->assertTrue(\file_exists($output));

        $output = $pdftkManager->save($input, $data, true);// create pdf and fdf
        $this->assertTrue(\file_exists($output));

        $output_fdf = \str_replace('.pdf.pdf', '.pdf.fdf', $output);// replace last part with fdf
        $this->assertTrue(\file_exists($output_fdf));

        // @todo find a way to issue a manageable error... that would assert the following
//        $output_error = $pdftkManager->clear($input, $data);
//        $this->assertTrue(\file_exists($output_error.'.log'));
    }

    public function getPdfFormData()
    {
        return array(
            array(
                'data' => array(
                    'name' => 'John Döê', // utf8 field
                    'address' => "123 street of the road".PHP_EOL."Dark alley", // multi line
                    'city' => 'Springfield',
                    'phone' => '12341234',
                    'Check Box 1' => 'On',
                ),
            ),
        );
    }

}
