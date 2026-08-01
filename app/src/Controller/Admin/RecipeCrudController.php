<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Recipe;
use App\Enum\Course;
use App\Enum\MealOccasion;
use App\Service\ImageUploader;
use App\Service\StorageUrlResolver;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RecipeCrudController extends AbstractCrudController
{
    public function __construct(
        private ImageUploader $imageUploader,
        private StorageUrlResolver $storageUrlResolver,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Recipe::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setDefaultSort(['name' => 'ASC']);
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name'),
            TextareaField::new('description'),
            ImageField::new('image')
                ->setUploadDir('public/uploads/images')
                ->setBasePath($this->storageUrlResolver->getBaseUrl())
                ->setUploadedFileNamePattern('[randomhash].[extension]')
                ->setRequired(false)
                ->setLabel('Image'),
            BooleanField::new('mastered'),
            ChoiceField::new('course')
                ->setChoices(array_combine(
                    array_map(fn(Course $c) => $c->label(), Course::cases()),
                    Course::cases()
                ))
                ->allowMultipleChoices(false)
                ->renderExpanded(false),
            ChoiceField::new('mealOccasions', 'Meal Occasions')
                ->setChoices(array_combine(
                    array_map(fn(MealOccasion $o) => $o->label(), MealOccasion::cases()),
                    MealOccasion::cases()
                ))
                ->allowMultipleChoices()
                ->renderExpanded(false)
                ->hideOnIndex(),
            AssociationField::new('pairsWith', 'Pairs With')
                ->autocomplete()
                ->hideOnIndex(),
            CollectionField::new('components')
                ->useEntryCrudForm(ComponentCrudController::class)
                ->setLabel('Components')
                ->hideOnIndex(),
            CollectionField::new('steps')
                ->useEntryCrudForm(StepCrudController::class)
                ->setLabel('Steps')
                ->hideOnIndex(),
            CollectionField::new('mistakes')
                ->useEntryCrudForm(MistakeCrudController::class)
                ->setLabel('Mistakes')
                ->hideOnIndex(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(
            ChoiceFilter::new('needsApproval', 'Approval Status')
                ->setChoices(['Pending' => '1', 'Approved' => '0'])
        );
    }

    public function configureActions(Actions $actions): Actions
    {
        $parseAction = Action::new('parseImages', 'Parse from Images')
            ->linkToRoute('admin_recipe_parse_images')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-success');

        $importAction = Action::new('import', 'Import Recipe')
            ->linkToRoute('admin_recipe_import')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-primary');

        $approveAction = Action::new('approveRecipe', 'Approve', 'fa fa-check')
            ->linkToCrudAction('approveRecipe')
            ->setCssClass('btn btn-sm btn-warning')
            ->displayIf(fn(Recipe $r) => (bool) $r->isNeedsApproval());

        $actions->add(Crud::PAGE_INDEX, $parseAction);
        $actions->add(Crud::PAGE_INDEX, $importAction);
        $actions->add(Crud::PAGE_INDEX, $approveAction);
        $actions->add(Crud::PAGE_DETAIL, $approveAction);

        return $actions;
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance instanceof Recipe) {
            $entityInstance->setNeedsApproval(false);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function approveRecipe(EntityManagerInterface $em): RedirectResponse
    {
        /** @var Recipe $recipe */
        $recipe = $this->getContext()->getEntity()->getInstance();
        $recipe->setNeedsApproval(false);
        $em->flush();

        $this->addFlash('success', sprintf('"%s" approved and published.', $recipe->getName()));

        return $this->redirect(
            $this->container->get(AdminUrlGeneratorInterface::class)
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }
}
