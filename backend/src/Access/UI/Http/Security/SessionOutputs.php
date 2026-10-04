<?php

namespace App\Access\UI\Http\Security;

use App\Access\Application\Query\Users;
use App\Access\UI\Http\Output\SessionOutput;
use App\Company\Application\Query\Companies;
use App\Shared\UI\Http\Security\Permission;

final class SessionOutputs
{
    public function __construct(
        private readonly Users $users,
        private readonly Companies $companies,
    ) {
    }

    public function of(SecurityUser $user): SessionOutput
    {
        $view = $this->users->get($user->companyId(), $user->userId());
        $company = $this->companies->view($user->companyId());

        return new SessionOutput($view->id, $view->email, $view->name, $view->role, $company->id, $company->legalName, $company->identificationNumber, $company->checkDigit, Permission::of($view->role));
    }
}
