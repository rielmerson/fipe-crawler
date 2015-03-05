<?php

namespace Fipe\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\ProgressBar;
use Fipe\Database;
use Fipe\Crawler;

class ExtrairCommand extends Command
{
    /**
     * @var \Fipe\Database
     */
    protected $db;

    protected function configure()
    {
        $this
            ->setName('fipe:extrair')
            ->setDescription('Extrai tabela informando ano e mês')
            ->addArgument(
                'ano',
                InputArgument::REQUIRED,
                'Informe ano'
            )
            ->addArgument(
                'mes',
                InputArgument::REQUIRED,
                'Informe mês (1 a 12)'
            )
            ->addArgument(
                'tipo',
                InputArgument::REQUIRED,
                'Informe tipo (1 = carro, 2 = moto, 3 = caminhão)'
            )
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $date = new \DateTime();

        if (!$input->getArgument('ano')) {
            $anos = array();
            for ($i = $date->format('Y'); $i >= 2001; $i--) {
                $anos[$i] = $i;
            }
            $question = new ChoiceQuestion(
                'Informe ano (ENTER para ' . $date->format('Y') . ')',
                $anos,
                $date->format('Y')
            );
            $input->setArgument('ano', $helper->ask($input, $output, $question));
        }

        if (!$input->getArgument('mes')) {
            $meses = array();
            foreach (range(1, 12) as $mes) {
                $meses[$mes] = $mes;
            }

            $question = new ChoiceQuestion(
                'Informe mês (1 a 12) (ENTER para ' . $date->format('m') . ')',
                $meses,
                $date->format('m')
            );
            $input->setArgument('mes', $helper->ask($input, $output, $question));
        }

        if (!$input->getArgument('tipo')) {
            $question = new ChoiceQuestion(
                'Informe tipo (1 = carro, 2 = moto, 3 = caminhão) (ENTER para Carro)',
                Crawler::$tipoVeiculosFull,
                1
            );
            $input->setArgument('tipo', $helper->ask($input, $output, $question));
        }

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mes  = str_pad($input->getArgument('mes'), 2, '0', STR_PAD_LEFT);
        $ano  = $input->getArgument('ano');
        $tiposRev = array_flip(Crawler::$tipoVeiculosFull);
        $tipoDesc = $input->getArgument('tipo');
        $tipo     = $tiposRev[$tipoDesc];

//        // green text
//        $output->writeln('<info>foo</info>');
//        // yellow text
//        $output->writeln('<comment>foo</comment>');
//        // black text on a cyan background
//        $output->writeln('<question>foo</question>');
//        // white text on a red background
//        $output->writeln('<error>foo</error>');

        //$this->extract($ano, $mes, $tipo, $output);

        $crawler = new Crawler();

        $output->writeln("<info>Recuperando tabelas para $mes/$ano...</info>");
        $tabela = $crawler->getTabelaByAnoMes($ano, $mes);
        if (null === $tabela) {
            $output->writeln("<error>Não encontrada tabela para $mes/$ano</error>");
            exit;
        }
        $output->writeln("<comment>Encontrada tabela $mes/$ano !</comment>");
        $output->writeln("");

        $descTabela = "tabela id=[{$tabela['id']}] $mes/$ano, ($tipo) $tipoDesc";
        $output->writeln("<info>Recuperando marcas para $descTabela...</info>");
        $marcas = $crawler->extractMarcas($tabela['id'], $tipo);
        $totalMarcas = count($marcas['results']);
        if ($totalMarcas === 0) {
            $output->writeln("<error>Não encontrada nenhuma marca para $descTabela !</error>");
            exit;
        }
        $output->writeln("<comment>Encontradas {$totalMarcas} marcas para $descTabela !</comment>");
        $output->writeln("");

        $output->writeln("<info>Recuperando modelos para {$totalMarcas} marcas -- $descTabela...</info>");
        $output->writeln("");
        $totalModelos = 0;
        $progress = new ProgressBar($output, $totalMarcas);
        $progress->setFormat(" %current%/%max% [%bar%] %ttmod% modelos extraídos");
        $progress->setMessage($totalModelos, 'ttmod');
        $progress->start();
        $modelos = array();
        foreach ($marcas['results'] as $marca) {
            $tmpModelos = $crawler->extractModelos($tabela['id'], $tipo, $marca['id']);
            $modelos[$marca['id']] = $tmpModelos['results'];
            $totalModelos += count($tmpModelos['results']);
            $progress->setMessage($totalModelos, 'ttmod');
            $progress->advance();
        }
        $progress->finish();
        $output->writeln("");
        $output->writeln("<comment>Encontrados {$totalModelos} modelos para {$totalMarcas} marcas -- $descTabela !</comment>");
        $output->writeln("");

        $output->writeln("<info>Recuperando veiculos para para {$totalModelos} -- $descTabela...</info>");
        $totalVeiculos = 0;
        $progress = new ProgressBar($output, $totalModelos);
        $progress->setFormat(" %current%/%max% [%bar%] %ttvei% veículos extraídos");
        $progress->setMessage($totalVeiculos, 'ttvei');
        $progress->start();
        $veiculos = array();
        foreach($modelos as $marcaId => $marcaModelos) {
            foreach($marcaModelos as $modelo) {
                $tmpVeiculos  = $crawler->extractVeiculos($tabela['id'], $tipo, $marcaId, $modelo['id'], true);
                array_merge($veiculos, $tmpVeiculos);
                $totalVeiculos += $tmpVeiculos['veiculosTotal'];
                $progress->setMessage($totalVeiculos, 'ttvei');
                $progress->advance();
            }
        }
        $progress->finish();


    }

    public function setDb(Database $db)
    {
        $this->db = $db;
    }


}