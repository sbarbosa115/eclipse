<?php

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Error\DuplicateCategoryName;
use App\Catalog\Domain\Repository\CategoryRepository;
use App\Shared\Application\Command\CommandHandler;

final class RenameCategoryHandler implements CommandHandler
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * @throws \App\Catalog\Domain\Error\CategoryNotFound
     * @throws DuplicateCategoryName
     */
    public function __invoke(RenameCategory $command): void
    {
        $category = $this->categories->get($command->companyId, $command->categoryId);
        $name = trim($command->name);
        if ($this->categories->nameTaken($command->companyId, $name, $category->id())) {
            throw new DuplicateCategoryName();
        }
        $category->rename($name);
    }
}
