<?php
/**
 * Date: 26.11.15
 * Time: 19:34
 */

namespace Skiner\GadgetBundle\Command;

use Propel\Runtime\Map\TableMap;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\HttpKernel\Kernel;

class AdminSimpleGeneratorCommand extends ContainerAwareCommand
{

	const OPTION_MODEL = 'model';

	/** @var Kernel */
	private $kernel;

	/** @var InputInterface */
	private $input;

	/** @var OutputInterface */
	private $output;

	protected function configure()
	{
		$this
			->setName('admin:generator')
			->setDefinition([
				new InputOption(self::OPTION_MODEL, null, InputOption::VALUE_OPTIONAL, 'Provide Model namespace to generate')
			])
		;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->input = $input;
		$this->output = $output;
		$model = str_replace('/', '\\', $input->getOption(self::OPTION_MODEL));

		try
		{
			$modelInstance = new $model();
		}
		catch (\Exception $e)
		{
			$output->writeln('' . $e->getMessage());

			return;
		}

		$this->kernel = $this->getContainer()->get('kernel');

		$dialog = $this->getHelper('question');

		$question = new Question('Please enter the name of the PHP directory App/AdminBundle/Controller/', '');
		$phpDirectory = $dialog->ask($input, $output, $question);
		$phpDirectory = trim(trim($phpDirectory, '/\\'));
		$phpDir = 'App/AdminBundle/Controller/' . $phpDirectory;
		$phpNamespace = str_replace('/', '\\', $phpDir);

		$question = new Question('Please enter the name of the Twig directory (default `' . $phpDirectory . '`) App/AdminBundle/Resources/views/', $phpDirectory);
		$twigDirectory = $dialog->ask($input, $output, $question);
		$twigDirectory = trim(trim($twigDirectory, '/\\'));
		$twigDir = 'App/AdminBundle/Resources/views/' . $twigDirectory;

		$formDir = 'App/AdminBundle/Form/' . $phpDirectory;
		$formNamespace = str_replace('/', '\\', $formDir);

		$className = explode('\\', get_class($modelInstance));
		$className = $className[sizeof($className) - 1];


		$twigListFileName    = lcfirst($className . 'List.html.twig');
		$twigEditionFileName = lcfirst($className . 'Edition.html.twig');
		$twigDeleteFileName  = lcfirst($className . 'Delete.html.twig');


		$this->generateListController($phpNamespace, $phpDir, $modelInstance, $twigDirectory, $className, $twigListFileName);
		$this->generateListView($className, $twigDir, $twigListFileName);

		$this->generateEditionController($phpNamespace, $phpDir, $modelInstance, $twigDirectory, $className, $twigEditionFileName, $formNamespace);
		$this->generateEditionView($className, $twigDir, $twigEditionFileName);

		$this->generateDeleteController($phpNamespace, $phpDir, $modelInstance, $twigDirectory, $className, $twigDeleteFileName);
		$this->generateDeleteView($className, $twigDir, $twigDeleteFileName);

		$this->generateForm($formDir, $modelInstance, $className);

		$this->printRoutingOnConsole($className, $phpDirectory);
	}

	private function printRoutingOnConsole($className, $phpDirectory)
	{
		$underscoreName = trim(strtolower(preg_replace('/([A-Z]{1})/', '_$1', $className)), '_');
		$classUrlname = str_replace('_', '-', $underscoreName);
		$lowerName = lcfirst($className);

		$routes = "admin_" . $underscoreName . "_list:
    path: /admin/$classUrlname/list
    defaults:
        _controller: AdminBundle:" . $phpDirectory . "/" . $className . "List:list
admin_" . $underscoreName . "_edition:
    path: /admin/$classUrlname/{" . $lowerName . "Id}/edition
    defaults:
        _controller: AdminBundle:" . $phpDirectory . "/" . $className . "Edition:index
    requirements:
        " . $lowerName . "Id: \\d+
admin_" . $underscoreName . "_create:
    path: /admin/$classUrlname/create
    defaults:
        _controller: AdminBundle:" . $phpDirectory . "/" . $className . "Edition:index
admin_" . $underscoreName . "_delete:
    path: /admin/$classUrlname/{" . $lowerName . "Id}/delete
    defaults:
        _controller: AdminBundle:" . $phpDirectory . "/" . $className . "Delete:index
    requirements:
        " . $lowerName . "Id: \\d+";

		$this->output->writeln("<info>$routes</info>");
	}

	/**
	 * @param $phpNamespace
	 * @param $phpDir
	 * @param $modelInstance
	 * @param $twigDirectory
	 * @param $className
	 * @param $twigListFileName
	 */
	private function generateListController($phpNamespace, $phpDir, $modelInstance, $twigDirectory, $className, $twigListFileName)
	{
		$phpListFileName = $className . 'ListController';

		$phpListTemplate = '<?php

/**
 * Date: ' . date('d.m.Y') . '
 * Time: ' . date('H:i') . '
 */
namespace ' . $phpNamespace . ';

use Symfony\Component\HttpFoundation\Response;
use System\SystemBundle\Controller\BaseController;
use ' . get_class($modelInstance) . 'Query;
use System\ModelBundle\Model\Role;

class ' . $phpListFileName . ' extends BaseController
{

	/**
	 * @return Response
	 */
	public function listAction()
	{
		$this->denyAccessUnlessGranted(Role::ROLE_ADMIN);

		$models = (new ' . $className . 'Query)->find();

		return $this->render(\'AdminBundle:' . $twigDirectory . ':' . $twigListFileName . '\', [
			\'models\' => $models,
		]);
	}

}';
		if (!file_exists($this->kernel->getRootDir() . '/../src/' . $phpDir))
		{
			mkdir($this->kernel->getRootDir() . '/../src/' . $phpDir, 0777, true);
		}

		$file = $this->kernel->getRootDir() . '/../src/' . $phpDir . '/' . $phpListFileName . '.php';

		if (file_exists($file))
		{
			$this->output->writeln('<error>PHP file exits ' . $file . '</error>');

			return;
		}

		file_put_contents($file, $phpListTemplate);
	}

	private function generateListView($className, $twigDir, $twigListFileName)
	{
		$underscoreName = trim(strtolower(preg_replace('/([A-Z]{1})/', '_$1', $className)), '_');

		$twigListTemplate = '{% extends \'::baseAdmin.html.twig\' %}

{% block title %}{{ \'header_' . $underscoreName . '_list\'|trans }}{% endblock %}

{% block headerLeft %}<h1>{{ \'header_' . $underscoreName . '_list\'|trans }}</h1>{% endblock %}

{% block headerRight %}
    <a href="{{ url(\'admin_' . $underscoreName . '_create\') }}" class="btn btn-xs btn-success">
        <span class="glyphicon glyphicon-plus"></span>
        {{ \'create_new_' . $underscoreName . '\'|trans }}
    </a>
{% endblock %}

{% block body %}

    <table class="table">
		{% for ' . lcfirst($className) . ' in models %}
			<tr>
				<td>{{ ' . lcfirst($className) . ' }}</td>
				<td>
					<div class="btn-group pull-right" role="group" aria-label="...">
						<a href="{{ url(\'admin_' . $underscoreName . '_edition\', {\'' . lcfirst($className) . 'Id\': ' . lcfirst($className) . '.id}) }}" class="btn btn-xs btn-danger">{{ \'edit\'|trans }}</a>
						<a href="{{ url(\'admin_' . $underscoreName . '_delete\', {\'' . lcfirst($className) . 'Id\': ' . lcfirst($className) . '.id}) }}" class="btn btn-xs btn-default">
							<i class="glyphicon glyphicon-trash"></i>
						</a>
					</div>
				</td>
			</tr>
        {% else %}
            <tr>
                <td>{{ \'no_results\'|trans }}</td>
            </tr>
        {% endfor %}
    </table>

{% endblock %}';

		if (!file_exists($this->kernel->getRootDir() . '/../src/' . $twigDir))
		{
			mkdir($this->kernel->getRootDir() . '/../src/' . $twigDir, 0777, true);
		}

		$file = $this->kernel->getRootDir() . '/../src/' . $twigDir . '/' . $twigListFileName;

		if (file_exists($file))
		{
			$this->output->writeln('<error>Twig file exits ' . $file . '</error>');

			return;
		}

		file_put_contents($file, $twigListTemplate);
	}


	private function generateEditionController($phpNamespace, $phpDir, $modelInstance, $twigDirectory, $className, $twigEditionFileName, $formNamespace)
	{
		$lowerName = lcfirst($className);
		$phpEditionFileName = $className . 'EditionController';

		$phpEditionTemplate = '<?php

/**
 * Date: ' . date('d.m.Y') . '
 * Time: ' . date('H:i') . '
 */
namespace ' . $phpNamespace . ';

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use System\SystemBundle\Controller\BaseController;
use ' . get_class($modelInstance) . ';
use ' . $formNamespace . '\\' . $className . 'Form;
use System\ModelBundle\Model\Role;

class ' . $phpEditionFileName . ' extends BaseController
{

	/**
	 * @ParamConverter("' . $lowerName . '", options={"mapping":{"' . $lowerName . 'Id":"id"}})
	 * @param Request $request
	 * @param ' . ucfirst($lowerName) . ' $' . $lowerName . '
	 *
	 * @return Response
	 */
	public function indexAction(Request $request, ' . ucfirst($lowerName) . ' $' . $lowerName . ' = null)
	{
		$this->denyAccessUnlessGranted(Role::ROLE_ADMIN);

		$' . $lowerName . ' = $' . $lowerName . ' ?: (new ' . ucfirst($lowerName) . ');

		$form = $this->createForm(' . ucfirst($lowerName) . 'Form::class, $' . $lowerName . ');

		if ($this->isPost())
		{
			$form->handleRequest($request);

			if ($form->isValid())
			{
				$' . $lowerName . '->save();

				$this->addFlash(\'success\', $this->get(\'translator\')->trans(\'successful_saved\'));

				return $this->redirectToRoute(\'admin_' . $lowerName . '_edition\', [\'' . $lowerName . 'Id\' => $' . $lowerName . '->getId()]);
			}
		}

		return $this->render(\'AdminBundle:' . $twigDirectory . ':' . $twigEditionFileName . '\', [
			\'form\'  => $form->createView(),
			\'' . $lowerName . '\' => $' . $lowerName . ',
		]);
	}

}';
		if (!file_exists($this->kernel->getRootDir() . '/../src/' . $phpDir))
		{
			mkdir($this->kernel->getRootDir() . '/../src/' . $phpDir, 0777, true);
		}

		$file = $this->kernel->getRootDir() . '/../src/' . $phpDir . '/' . $phpEditionFileName . '.php';

		if (file_exists($file))
		{
			$this->output->writeln('<error>PHP file exits ' . $file . '</error>');

			return;
		}

		file_put_contents($file, $phpEditionTemplate);
	}

	private function generateEditionView($className, $twigDir, $twigEditionFileName)
	{
		$underscoreName = trim(strtolower(preg_replace('/([A-Z]{1})/', '_$1', $className)), '_');

		$twigEditionTemplate = '{% extends \'::baseAdmin.html.twig\' %}

{% form_theme form \'bootstrap_3_horizontal_layout.html.twig\' %}

{% block title %}{{ \'header_edition_single_' . $underscoreName . '\'|trans }}{% endblock %}

{% block headerLeft %}
    <h1>{{ \'header_edition_single_' . $underscoreName . '\'|trans }}</h1>
    <h3><a href="{{ url(\'admin_' . $underscoreName . '_list\') }}">« {{ \'back\'|trans }}</a></h3>
{% endblock %}

{% block headerRight %}
    <a href="{{ url(\'admin_' . $underscoreName . '_create\') }}" class="btn btn-xs btn-success">
        <span class="glyphicon glyphicon-plus"></span>
        {{ \'create_new_' . $underscoreName . '\'|trans }}
    </a>
{% endblock %}

{% block body %}

    {{ form_start(form) }}
    <table class="table">
        <tr>
            <td>{{ form_widget(form) }}</td>
        </tr>
        <tr>
            <td>
                <button class="btn btn-primary">{{ \'save\'|trans }}</button>
            </td>
        </tr>
    </table>
    {{ form_end(form) }}

{% endblock %}';

		if (!file_exists($this->kernel->getRootDir() . '/../src/' . $twigDir))
		{
			mkdir($this->kernel->getRootDir() . '/../src/' . $twigDir, 0777, true);
		}

		$file = $this->kernel->getRootDir() . '/../src/' . $twigDir . '/' . $twigEditionFileName;

		if (file_exists($file))
		{
			$this->output->writeln('<error>Twig file exits ' . $file . '</error>');

			return;
		}

		file_put_contents($file, $twigEditionTemplate);
	}


	private function generateDeleteController($phpNamespace, $phpDir, $modelInstance, $twigDirectory, $className, $twigDeleteFileName)
	{
		$lowerName = lcfirst($className);
		$phpDeleteFileName = $className . 'DeleteController';

		$phpEditionTemplate = '<?php

/**
 * Date: ' . date('d.m.Y') . '
 * Time: ' . date('H:i') . '
 */
namespace ' . $phpNamespace . ';

use Propel\Runtime\Exception\PropelException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use System\SystemBundle\Controller\BaseController;
use ' . get_class($modelInstance) . ';
use System\ModelBundle\Model\Role;

class ' . $phpDeleteFileName . ' extends BaseController
{

	/**
	 * @ParamConverter("' . $lowerName . '", options={"mapping":{"' . $lowerName . 'Id":"id"}})
	 * @param Request $request
	 * @param ' . $className . ' $' . $lowerName . '
	 *
	 * @return Response
	 * @throws \Exception
	 * @throws PropelException
	 */
	public function indexAction(Request $request, ' . $className . ' $' . $lowerName . ')
	{
		$this->denyAccessUnlessGranted(Role::ROLE_ADMIN);

		if ($this->isPost() && $request->request->has(\'delete\'))
		{
			$' . $lowerName . '->delete();

			$this->addFlash(\'success\', \'Deleted !\');

			return $this->redirectToRoute(\'admin_' . $lowerName . '_list\');
		}

		return $this->render(\'AdminBundle:' . $twigDirectory . ':' . $twigDeleteFileName . '\', [
			\'' . $lowerName . '\' => $' . $lowerName . ',
		]);
	}

}';
		if (!file_exists($this->kernel->getRootDir() . '/../src/' . $phpDir))
		{
			mkdir($this->kernel->getRootDir() . '/../src/' . $phpDir, 0777, true);
		}

		$file = $this->kernel->getRootDir() . '/../src/' . $phpDir . '/' . $phpDeleteFileName . '.php';

		if (file_exists($file))
		{
			$this->output->writeln('<error>PHP file exits ' . $file . '</error>');

			return;
		}

		file_put_contents($file, $phpEditionTemplate);
	}

	private function generateDeleteView($className, $twigDir, $twigDeleteFileName)
	{
		$underscoreName = trim(strtolower(preg_replace('/([A-Z]{1})/', '_$1', $className)), '_');

		$twigDeleteTemplate = '{% extends \'::baseAdmin.html.twig\' %}

{% block title %}{{ \'header_edition_single_' . $underscoreName . '\'|trans }}{% endblock %}

{% block headerLeft %}
    <h1>{{ \'header_are_you_sure\'|trans }}</h1>
    <h3><a href="{{ url(\'admin_' . $underscoreName . '_list\') }}">« {{ \'back\'|trans }}</a></h3>
{% endblock %}

{% block body %}

    <div>
        <h3>{{ \'do_you_really_want_delete\'|trans }} ?</h3>
        <div class="col-lg-3">
            <a class="btn btn-lg btn-danger" href="{{ url(\'admin_' . $underscoreName . '_list\') }}">{{ \'cancel\'|trans }}</a>
        </div>
        <div class="col-lg-3">
            <form method="post">

                <button type="submit" name="delete" class="btn btn-lg btn-default">
                    <i class="glyphicon glyphicon-trash"></i>
                    {{ \'delete\'|trans }}
                </button>

            </form>
        </div>
    </div>

{% endblock %}';

		if (!file_exists($this->kernel->getRootDir() . '/../src/' . $twigDir))
		{
			mkdir($this->kernel->getRootDir() . '/../src/' . $twigDir, 0777, true);
		}

		$file = $this->kernel->getRootDir() . '/../src/' . $twigDir . '/' . $twigDeleteFileName;

		if (file_exists($file))
		{
			$this->output->writeln('<error>Twig file exits ' . $file . '</error>');

			return;
		}

		file_put_contents($file, $twigDeleteTemplate);
	}

	private function generateForm($formDir, $modelInstance, $className)
	{
		$formFileName = $className . 'Form';
		$underscoreName = trim(strtolower(preg_replace('/([A-Z]{1})/', '_$1', $className)), '_');

		$phpNamespace = str_replace('/', '\\', $formDir);

		$modelMap = get_class($modelInstance);
		$modelMap = str_replace($className, 'Map\\' . $className . 'TableMap', $modelMap);
		/** @var TableMap $modelMap */
		$modelMap = new $modelMap();


		$fields = '';

		foreach ($modelMap->getColumns() as $column)
		{
			$fields .= "			->add('" . $column->getName() . "', TextType::class, [])\n";
		}


		$formTemplate = '<?php
/**
 * Date: ' . date('d.m.Y') . '
 * Time: ' . date('H:i') . '
 */

namespace ' . $phpNamespace . ';

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ' . get_class($modelInstance) . ';

class ' . $className . 'Form extends AbstractType
{

	/**
	 * {@inheritDoc}
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
' . $fields .'
		;
	}

	/**
	 * @param OptionsResolver $resolver
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver
			->setDefaults([
					\'data_class\' => ' . $className . '::class
			]);
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return \'' . $underscoreName . '\';
	}
}';

		if (!file_exists($this->kernel->getRootDir() . '/../src/' . $formDir))
		{
			mkdir($this->kernel->getRootDir() . '/../src/' . $formDir, 0777, true);
		}

		$file = $this->kernel->getRootDir() . '/../src/' . $formDir . '/' . $formFileName . '.php';

		if (file_exists($file))
		{
			$this->output->writeln('<error>PHP file exits ' . $file . '</error>');

			return;
		}

		file_put_contents($file, $formTemplate);
	}


}