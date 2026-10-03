<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Error\DuplicateCategoryName;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Repository\CategoryRepository;
use App\Shared\Application\Command\CommandHandler;
use Symfony\Component\Uid\Uuid;

final class CreateCategoryHandler implements CommandHandler
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * @throws DuplicateCategoryName
     */
    public function __invoke(CreateCategory $command): Uuid
    {
        $name = trim($command->name);
        if ($this->categories->nameTaken($command->companyId, $name)) {
            throw new DuplicateCategoryName();
        }
        $category = new Category($command->companyId, $name);
        $this->categories->add($category);

        return $category->id();
    }
}
