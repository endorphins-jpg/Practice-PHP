<?php

namespace System\CLI;

class CLI
{
    /**
     * Mapeia o comando recebido para uma função correspondente
    */
    public function __construct(array $comando)
    {
        match ($comando[1]??false) {
            'iniciar', 'servir', 'serve' => $this->iniciar($comando[2] ?? '8080'),
            'criar', 'fazer', 'gerar' => $this->criar($comando[2]??'', $comando[3]??''),
            'testar' => $this->testar($comando[2]??''),
            'migrar' => $this->migrar($comando[2]??''),
            'ajuda'=> $this->ajuda(),
            default => [$this->imprimir("Você precisa informar algum comando válido."), $this->ajuda()],
        };
    }


    
    /**
    * Inicia um servidor embutido do PHP para a pasta public 
    * na porta desejada (padrão 8080)
    * @author Brunoggdev
    */
    private function iniciar(string $porta):void
    {
        exec("php -S localhost:$porta -t public");
    }



    /**
    * Cria um novo arquivo com as propriedades desejadas
    * @author Brunoggdev
    */
    private function criar(string $tipo_arquivo, string $nome):void
    {
        if( empty($tipo_arquivo) ){
            $this->imprimir("Você deve informar um tipo de arquivo para ser gerado (controller ou model)", 0);
            $this->imprimir("Ex.: php forja criar Model Usuario");
            exit;
        }

        if( empty($nome) ){
            $this->imprimir("\033[93mVocê deve informar um nome pro arquivo depois do tipo.\033[0m", 0);
            $this->imprimir("\033[93mEx.: php forja criar model UsuariosModel.\033[0m");
            exit;
        }

        $tipo_arquivo = ucfirst($tipo_arquivo);
        if($tipo_arquivo !== 'Tabela'){
            $nome = ucfirst($nome);
        }

        $caminho = match ($tipo_arquivo) {
            'Controller' =>  PASTA_RAIZ . 'app/Controllers/',
            'Model' => PASTA_RAIZ . 'app/Models/',
            'Filtro' => PASTA_RAIZ . 'app/Filtros/',
            'Tabela' => PASTA_RAIZ . 'app/Database/',
        };

        $template = require "templates/$tipo_arquivo.php";
        $arquivo = str_replace('{nome}', $nome, $template);

        if($tipo_arquivo === 'Tabela'){
            $nome = date('Y-m-d-His_') . $nome;
        }

        if ( file_put_contents("$caminho$nome.php", $arquivo) ) {
            $resposta = "\033[92m$tipo_arquivo $nome criado com sucesso.\033[0m";
        } else {
            $resposta = "\033[91mAlgo deu errado ao gerar o $tipo_arquivo.";
        }

        $this->imprimir($resposta);
    }



    /**
    * Executa todas as closures de teste e imprime seus resultados
    * @author Brunoggdev
    */
    public function testar(string $caminho):void
    {
        $caminho = 'app/Testes/' . $caminho;
        // tomando controle dos erros nativos do php
        set_error_handler(function($errno, $errstr, $errfile, $errline){
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        // Se for um diretorio, busque todos os arquivos dentro
        if( is_dir($caminho) ){
            $arquivos = array_merge(
                glob($caminho . '*.php'),
                glob($caminho . '**/*.php')
            );

            foreach ($arquivos as $arquivo) {
                require_once $arquivo;
            }

        }else{
            // se não, busque apenas o arquivo informado
            try{
                require_once $caminho;
            }catch(\ErrorException){
                $this->imprimir('Arquivo não encontrado.');
                exit;
            }
        }

        $testesPassaram = 0;
        $testesFalhaaram = 0;
        foreach ($testar->testes() as $i => $teste) {

            try {
                $resultado = call_user_func($teste['funcao']);
            } catch (\Throwable $th) {
                $resultado = false;
                $erro = 
                "-> \033[1m Erro encontrado: \033[0m" . $th->getMessage() . "\n" . 
                "  -> \033[1m Na linha: \033[0m" . $th->getLine() . "\n" . 
                "  -> \033[1m Do arquivo: \033[0m" . $th->getFile() . "\n\n";
            }

            if($resultado === true){
                $status = "\033[42mPassou.\033[0m";
                $testesPassaram++;
            }else{
                $status = "\033[41mFalhou.\033[0m";
                $testesFalhaaram++;
            }



            $trilha = str_repeat('.', 80 - mb_strlen($teste['descricao']) - mb_strlen($status));

            $relatorio = sprintf("%d - %s %s %s", ($i+1), "Testa se $teste[descricao]", $trilha, $status);
            
            $this->imprimir($relatorio, isset($erro) ? 0 : 1);

            if(isset($erro)){
                $this->imprimir($erro, 0);
                unset($erro);
            }
        }
        echo "\n";
        $this->imprimir("Passaram: $testesPassaram.", 0);
        $this->imprimir("Falharam: $testesFalhaaram.");
    }


    /**
    * Executa as sql's de criação de tabelas
    * @author Brunoggdev
    */
    public function migrar(string $caminho):void
    {
        $caminho = 'app/Database/' . $caminho;

        // Se for um diretorio, busque todos os arquivos dentro
        if( is_dir($caminho) ){
            $tabelas = array_merge(
                glob($caminho . '*.php'),
                glob($caminho . '**/*.php')
            );

            // Executa a sql retornada por cada arquivo
            foreach ($tabelas as $tabela) {
                $sql = (string) require $tabela;

                if (stripos($sql, 'CREATE TABLE') !== 0){
                    throw new \Exception('Sql informada não é válida para esta operação.');
                }

                (new \System\Database\Database)->query($sql);
            }

        }else{
            // se não, busque apenas o arquivo informado
            try{
                $sql = (string) require $caminho;

                if (stripos($sql, 'CREATE TABLE') !== 0){
                    throw new \Exception('Sql informada não é válida para esta operação.');
                }

                (new \System\Database\Database)->query($sql);
            }catch(\ErrorException){
                $this->imprimir('Arquivo não encontrado.');
                exit;
            }
        }

        echo "\n";
        $this->imprimir('Tabela(s) criada(s) com sucesso!');
    }



    /**
    * Imprime a resposta desejada no terminal
    * @author Brunoggdev
    */
    private function imprimir(string $resposta, int $eol = 2)
    {
        echo "\n# $resposta" . str_repeat(PHP_EOL, $eol);
    }



    /**
    * Imprime uma sessão de ajuda listando os 
    * comandos disponíveis e como usa-los;
    * @author Brunoggdev
    */
    private function ajuda()
    {
        $this->imprimir('-------------------------------------------------------------------------------------------------------', 0);
        $this->imprimir('| Comandos |                 Parametros                  |                  Exemplos                  |', 0);
        $this->imprimir('-------------------------------------------------------------------------------------------------------', 0);
        $this->imprimir('|  inciar  | porta (opcional, 8080 padrão)               | php forja iniciar (8888)                   |', 0);
        $this->imprimir('-------------------------------------------------------------------------------------------------------', 0);
        $this->imprimir('|  criar   | [controller, model, filtro, tabela] + nome  | php forja criar controller NotasController |', 0);
        $this->imprimir('-------------------------------------------------------------------------------------------------------', 0);
        $this->imprimir('|  testar  | pasta/arquivo especifico (opcional)         | php forja testar (HefestosPHP)             |', 0);
        $this->imprimir('-------------------------------------------------------------------------------------------------------', 0);
        $this->imprimir('|  migrar  | pasta/arquivo especifico (opcional)         | php forja migrar (usuarios.php)            |', 0);
        $this->imprimir('-------------------------------------------------------------------------------------------------------');
    }
}