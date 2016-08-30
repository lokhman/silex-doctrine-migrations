<?php

namespace Lokhman\Silex\Provider;

use Pimple\Container;
use Silex\Application;
use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Symfony\Component\Console\Application as Console;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Migrations\OutputWriter;

/**
 * Silex service provider for Doctrine Migrations library.
 *
 * @author Alexander Lokhman <alex.lokhman@gmail.com>
 * @link https://github.com/lokhman/silex-doctrine-migrations
 */
class DoctrineMigrationsServiceProvider implements ServiceProviderInterface, BootableProviderInterface {

    /**
     * The console application.
     *
     * @var Console
     */
    protected $console;

    /**
     * Constructor.
     *
     * @param Console $console
     */
    public function __construct(Console $console) {
        $this->console = $console;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $app) {
        $app['migrations.output_writer'] = new OutputWriter(
            function ($message) {
                $output = new ConsoleOutput();
                $output->writeln($message);
            }
        );
        $app['migrations.directory']  = null;
        $app['migrations.namespace']  = null;
        $app['migrations.name']       = 'Migrations';
        $app['migrations.table_name'] = '_migrations';
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app) {
        $commands = [
            'Doctrine\DBAL\Migrations\Tools\Console\Command\ExecuteCommand',
            'Doctrine\DBAL\Migrations\Tools\Console\Command\GenerateCommand',
            'Doctrine\DBAL\Migrations\Tools\Console\Command\MigrateCommand',
            'Doctrine\DBAL\Migrations\Tools\Console\Command\StatusCommand',
            'Doctrine\DBAL\Migrations\Tools\Console\Command\VersionCommand',
        ];

        $helperSet = new HelperSet(array(
            'connection' => new ConnectionHelper($app['db']),
            'question'   => new QuestionHelper(),
        ));

        if (isset($app['orm.em'])) {
            // if we use ORM, helper set will require EntityManagerHelper from Doctrine ORM package
            $helperSet->set(new \Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper($app['orm.em']), 'em');
            $commands[] = 'Doctrine\DBAL\Migrations\Tools\Console\Command\DiffCommand';
        }

        $this->console->setHelperSet($helperSet);

        $configuration = new Configuration($app['db'], $app['migrations.output_writer']);
        $configuration->setMigrationsDirectory($app['migrations.directory']);
        $configuration->setMigrationsNamespace($app['migrations.namespace']);
        $configuration->setName($app['migrations.name']);
        $configuration->setMigrationsTableName($app['migrations.table_name']);

        $configuration->registerMigrationsFromDirectory($app['migrations.directory']);

        foreach ($commands as $name) {
            /** @var AbstractCommand $command */
            $command = new $name();
            $command->setMigrationConfiguration($configuration);
            $this->console->add($command);
        }
    }

}
