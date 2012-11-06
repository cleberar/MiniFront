<?php
/**
 * MiniFront :: Simples Compilador Front end em PHP
 *
 * Um Simples Script para unificar e comprimir arquivos JavaScript e CSS
 * ideal para pequenos projetos, ou nao ;)
 *
 * PHP version 5
 * 
 * @author    Cleber Rodrigues <scafe@cleberrodrigues.com.br>
 * @copyright Copyright 2012 Cleber Rodrigues
 * @license   http://www.apache.org/licenses/LICENSE-2.0.html
 */

try {

    $minifront = new MiniFront();
    return (php_sapi_name() == "cli")
        ? $minifront->compiler()
        : $minifront->web($_SERVER['REQUEST_URI']);

} catch (Exception $e) {

    if (php_sapi_name() == "cli") {
        printf ("Error %s () %s \n", $e->getMessage(), $e->getCode());
    } else {
        header("HTTP/1.1 500 Internal Server Error");
        print $e->getMessage();
        return true;
    }
}

/**
 * Classe que ira unificar os arquivos e/ou compilar
 * 
 */
class MiniFront {

    /**
     * Diretorio para cache ds arquivos durante a compilacao
     * @var String
     */
    private $_dirCompiled = "compiled";

    /**
     * Nome do arquivo de build com a definicao de arquivos
     * @var String
     */
    private $_buildFile = "build.json";

    /**
     * Objeto com a estrutura de arquivos, que serao unificados/compilados
     * @var Object
     */
    private $_build = false;

    /**
     * construtor, realiza o parse do arquivo de build e carrega
     * objeto com os dados
     *
     * @return void
     */
    public function __construct()
    {
        $this->_buid = self::_buildParse();
    }

    /**
     * Metodo que ira executar a compilacao dos arquivos
     *
     * @return void
     */
    public function compiler()
    {

        // log
        printf("\n Iniciando compilacao de arquivos \n");
        printf("\n%'=80s\n", "|");

        // verificando se existe objeto de buid
        printf(" %-'.71s ", "verificando build");
        ($this->_buid) ? printf(" feito\n") : exit("falhou\n");

        // se o diretorio que cache da compilacao nao existir, criamos
        if (!file_exists($this->_dirCompiled)) {
            printf (" - criando diretorio de cache ... ");
            (mkdir($this->_dirCompiled)) ? printf("feito\n") : exit("falhou\n");
        } else {
            printf (" %-'.71s ", "diretorio de cache");
            printf(" feito\n");
        }

        print "\n";
        // percorrendo os arquivos de build 
        foreach($this->_buid as $type => $content) {

            // define metodo de compressao
            $method = "_{$type}Compress";

            // se o metodo de compress existir
            if (is_callable(array($this, $method))) {

                printf(" - [%s] verificando arquivos  ", strtoupper($type));
                $list = self::_uniqueFile($type, $content);

                // se nao existir arquivos a compilar, pula para o proximo
                if (count($list) == 0) {
                    continue;
                }

                $this->$method($list);
            }
        }

        // limpa os arquivos criados
        $this->_rrmdir($this->_dirCompiled);
    }

    /**
     * Exibindo a estrutura pela Web
     *
     * @param Strinf $uri caminho do arquivo acessado
     *
     * @return void
     */
    public function web($uri)
    {
        // removendo parametros, obtendo assim somente o nome do arquivo
        $file = str_replace(strstr($uri, "?"), "", $uri);

        // parse do arquivo enviado
        $parsefile = pathinfo($file);

        $extension = $parsefile["extension"];
        $filename  = $parsefile["filename"];
        $build = $this->_buid;

        if ((!$extension || !$filename)
            || (!$build->$extension || !$build->$extension->$filename)
        ) {
            return false;
        }

        $content = "";
        foreach($build->$extension->$filename as $filename) {
            if (!file_exists($filename)) {
                throw new Exception(sprintf("file %s not found", $filename));
            }
            $content .= file_get_contents($filename);
        }

        // cria cabecalho
        header('Content-Type: text/' . ($extension == "js" ? "javascript" : $extension));
        header('Content-Length: ' . strlen($content));
        print $content;
        return true;
    }

    /**
     * Unifica todos os arquivos em um arquivo base, salvando diretorio
     * de compilacao. Retorna lista com os arquivo que foram gerados
     *
     * @param String $ext   Extensao arquivo CSS / JS
     * @param Array  $files Lista de arquivos 
     *
     * @return Array com lista de aqruivos
     */
    private function _uniqueFile($ext, $files)
    {

        $returnList = array();
        foreach($files as $name => $listContent) {

            // arquivo que ira conter todos os outros
            $fileAll = $this->_dirCompiled . "/" . $name . "." . $ext;

            // se o aqruivo ja existir, remove pois vamos regera-lo
            if (file_exists($fileAll)) {
                unlink($fileAll);
            }

            printf("\n   * unificando arquivo %s \n", $fileAll);

            // percorre a lista de conteudos deste arquivos
            foreach($listContent as $filename) {

                // se o arquivo existir, adicionamos o conteudo ao que ja exista
                if (file_exists($filename)) {
                    printf("      %-'.66s ", $filename);

                    if (file_put_contents(
                        $fileAll ,
                        file_get_contents($filename),
                        FILE_APPEND
                    )) {
                        printf(" %'6s\n", "feito");
                    } else {
                        printf(" %'6s\n", "falhou");
                    }
                }
            }

            // adiciona na lista
            array_push($returnList, $name);
        }

        return $returnList;
    }

    /**
     * Metodo que comprime arquivos CSS
     *
     * @param Array $listFile Lista de arquivos css que iremos compilar
     *
     * @return void
     */
    private function _cssCompress($listFile = array())
    {

        // se nao existir diretorio de css, criamos
        if (!file_exists("css")) {
            mkdir("css");
        }

        foreach ($listFile as $file) {

            $file = "$file.css";
            printf("\n   * comprimindo %-'.55s ", $file);

            if (!file_exists($this->_dirCompiled . "/" . $file)) {
                printf(" %'.6s  \n", "falhou");
                continue;
            }

            $code = file_get_contents($this->_dirCompiled . "/" . $file);
            $code = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $code);
            $code = str_replace(array("\r\n", "\r", "\n", "\t", ''), '', $code);

            if (file_put_contents("css/" . $file , $code)) {
                printf(" %'6s\n", "feito");
            } else {
                printf(" %'6s\n", "falhou");
            }
        }

        print "\n";
    }

    /**
     * Metodo que comprime arquivos JS
     *
     * @param Array $listFile Lista de arquivos css que iremos compilar
     *
     * @return void
     */
    private function _jsCompress($listFile = array())
    {

        // se nao existir diretorio de js criamos
        if (!file_exists("js")) {
            mkdir("js");
        }

        foreach ($listFile as $file) {

            $file = "$file.js";

            printf("\n   * comprimindo %-'.55s ", $file);
            if (!file_exists($this->_dirCompiled . "/" . $file)) {
                printf(" %'.6s  \n", "falhou");
                continue;
            }

            $code = file_get_contents($this->_dirCompiled . "/" .$file);

            $args = array(
                'compilation_level' => 'SIMPLE_OPTIMIZATIONS',
                'output_format'     => 'text',
                'output_info'       => 'compiled_code',
                'js_code'           => $code
            );

            $content = http_build_query($args);
            if (!$fp = fsockopen("closure-compiler.appspot.com", 80)) {
                throw new Exception("Erro ao executar closure-compiler");
            }

            fputs($fp, "POST /compile HTTP/1.1\r\n");
            fputs($fp, "Host: closure-compiler.appspot.com\r\n");
            fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
            fputs($fp, "Content-length: " . strlen($content) . "\r\n");
            fputs($fp, "Connection: close\r\n\r\n");
            fputs($fp, $content); 

            $data = stream_get_contents($fp);
            fclose($fp);

            $data = substr($data, (strpos($data, "\r\n\r\n") + 4));

            if (file_put_contents("js/" . $file , $data)) {
                printf(" %'6s\n", "feito");
            } else {
                printf(" %'6s\n", "falhou");
            }

            print "\n";
        }
    }

    /**
     * Realiza o parse do arquivo de build identificando os arquivos
     * de que devem ser unificados ou comprimidos
     *
     * @return void
     */
    private function _buildParse()
    {

        if (!file_exists($this->_buildFile)) {
            throw new Exception("arquivo de build (build.json) nao encontrado");
        }

        if (!$build = file_get_contents($this->_buildFile)) {
            throw new Exception(
                'erro ao ler arquivo de build: ' . $this->_buildFile
            );
        }

        if (!$json = json_decode($build)) {
            throw new Exception(
                'erro ao parsear json do arquivo de build: ' . $this->_buildFile
            );
        }

        return $json;
    }

    /**
     * Remove um diretorio recursivamente
     *
     * @param String $dir path do diretorio
     *
     * @return void
     */
    private function _rrmdir($dir = "") {

        // se nao for enviado um diretorio, nao faz nada
        if (!is_dir($dir)) {
            return;
        }

        // percorre os arquivos
        foreach (glob($dir . '/*') as $file) {

            // se for diretorio, acessa recursivamente
            // senao remove o arquivo
            if (is_dir($file)) {
                $this->_rrmdir($file);
            } else  {
                unlink($file);
            }
        }

        rmdir($dir);
    }
}