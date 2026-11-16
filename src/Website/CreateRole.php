<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, TrailSubject, Website, WebsiteComponents};
use PhpPgAdmin\Application\DTO\Role as DTORole;
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\DDD\ValueObjects\Role;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

class CreateRole extends Website
{
    public function __construct()
    {
        parent::__construct();

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
        }
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);

        $body->appendChild(WebsiteComponents::buildTopBar($dom));
        $body->appendChild(WebsiteComponents::buildTrail($dom, [TrailSubject::Server]));

        $serverId = RequestParameter::getString('server') ?? '';

        $h2 = $dom->createElement('h2');
        $h2->appendChild($dom->createTextNode(_('Create role')));
        $helpLink = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.role.create',
                'server' => $serverId,
            ],
        );
        $h2->appendChild($helpLink);
        $body->appendChild($h2);

        if ($this->message !== '') {
            $body->appendChild(WebsiteComponents::buildMessage($dom, $this->message));
        }

        $form = $dom->createElement('form');
        $form->setAttribute('method', 'post');

        $form->appendChild(self::buildCreateOrEditRoleTable($dom, $serverId, DTORole::createFromFormRequest()));
        $form->appendChild(self::buildCreateOrEditFormParagraphButtonsTable($dom, $serverId));

        $body->append($form);

        return $body;
    }

    protected static function buildCreateOrEditFormParagraphButtonsTable(
        \DOMDocument $dom,
        string $serverId,
        ?Role $role = null,
    ): \DOMElement {
        $p = $dom->createElement('p');

        $inputAction = $dom->createElement('input');
        $inputAction->setAttribute('type', 'hidden');
        $inputAction->setAttribute('name', 'action');
        $inputAction->setAttribute('value', 'save_create');
        $inputServer = $dom->createElement('input');
        $inputServer->setAttribute('type', 'hidden');
        $inputServer->setAttribute('name', 'server');
        $inputServer->setAttribute('value', $serverId);
        $inputSubmitCreate = $dom->createElement('input');
        $inputSubmitCreate->setAttribute('type', 'submit');
        $inputSubmitCreate->setAttribute('name', !is_null($role) ? 'alter' : 'create');
        $inputSubmitCreate->setAttribute('value', !is_null($role) ? _('Alter') : _('Create'));
        $inputSubmitCancel = $dom->createElement('input');
        $inputSubmitCancel->setAttribute('type', 'submit');
        $inputSubmitCancel->setAttribute('name', 'cancel');
        $inputSubmitCancel->setAttribute('value', _('Cancel'));

        $p->appendChild($inputAction);
        $p->appendChild($inputServer);
        $p->appendChild($inputSubmitCreate);
        $p->appendChild($dom->createEntityReference('nbsp'));
        $p->appendChild($inputSubmitCancel);

        return $p;
    }

    protected static function buildCreateOrEditRoleTable(
        \DOMDocument $dom,
        string $serverId,
        ?Role $role = null,
    ): \DOMElement {
        $table = $dom->createElement('table');
        $table->setAttribute('class', 'form-table');
        $tBody = $dom->createElement('tbody');

        $rolename = !is_null($role)
            ? $role->Name
            : '';

        if (empty($rolename)) {
            $rolename = RequestParameter::getString(Role::FORM_ID_NAME);
        }

        $nameSpecs = [
            'id' => Role::FORM_ID_NAME,
            'label-text' => _('Name'),
            'value' => [
                'content' => $rolename,
                'max-length' => 63,
                'readonly' => !is_null($role),
            ],
        ];
        $trName = WebsiteComponents::buildTableRowForInputFormular($dom, $nameSpecs);

        $passwordSpecs = [
            'id' => Role::FORM_ID_PASSWORD,
            'label-text' => _('Password'),
            'value' => [
                'content' => RequestParameter::getString(Role::FORM_ID_PASSWORD) ?? '',
                'type' => 'password',
            ],
        ];
        $trPassword = WebsiteComponents::buildTableRowForInputFormular($dom, $passwordSpecs);

        $confirmPasswordSpecs = [
            'id' => Role::FORM_ID_PASSWORD_CONFIRMATION,
            'label-text' => _('Confirm'),
            'value' => [
                'content' => RequestParameter::getString(Role::FORM_ID_PASSWORD_CONFIRMATION) ?? '',
                'type' => 'password',
            ],
        ];
        $trConfirm = WebsiteComponents::buildTableRowForInputFormular($dom, $confirmPasswordSpecs);

        $isSuperuserSpecs = [
            'id' => 'formSuper',
            'label-text' => _('Superuser?'),
            'value' => [
                'content' => !is_null($role) ? $role->IsSuperuser : false,
                'type' => 'bool',
            ],
        ];
        $trSuperuser = WebsiteComponents::buildTableRowForCheckboxFormular($dom, $isSuperuserSpecs);

        $canCreateDbSpecs = [
            'id' => 'formCreateDB',
            'label-text' => _('Create DB?'),
            'value' => [
                'content' => !is_null($role) ? $role->CanCreateDb : false,
                'type' => 'bool',
            ],
        ];
        $trCreateDb = WebsiteComponents::buildTableRowForCheckboxFormular($dom, $canCreateDbSpecs);

        $canCeateRoleSpecs = [
            'id' => 'formCreateRole',
            'label-text' => _('Can create role?'),
            'value' => [
                'content' => !is_null($role) ? $role->CanCreateRole : false,
                'type' => 'bool',
            ],
        ];
        $trCreateRole = WebsiteComponents::buildTableRowForCheckboxFormular($dom, $canCeateRoleSpecs);

        $inheritsSpecs = [
            'id' => 'formInherits',
            'label-text' => _('Inherits privileges?'),
            'value' => [
                'content' => !is_null($role) ? $role->CanInheritRights : false,
                'type' => 'bool',
            ],
        ];
        $trInherits = WebsiteComponents::buildTableRowForCheckboxFormular($dom, $inheritsSpecs);

        $canLoginSpecs = [
            'id' => 'formCanLogin',
            'label-text' => _('Can login?'),
            'value' => [
                'content' => !is_null($role) ? $role->CanLogin : false,
                'type' => 'bool',
            ],
        ];
        $trCanLogin = WebsiteComponents::buildTableRowForCheckboxFormular($dom, $canLoginSpecs);

        $maxConnectionsSpecs = [
            'id' => 'formConnLimit',
            'label-text' => _('Connection limit'),
            'value' => [
                'content' => !is_null($role) ? $role->ConnectionLimit : null,
                'type' => 'number',
            ],
        ];
        $trMaxConnections = WebsiteComponents::buildTableRowForInputFormular($dom, $maxConnectionsSpecs);

        $expiresSpecs = [
            'id' => 'formExpires',
            'label-text' => _('Expires'),
            'value' => [
                'content' => !is_null($role) ? $role->Expires : null,
                'type' => 'datetime-local',
            ],
        ];
        $trExpires = WebsiteComponents::buildTableRowForInputFormular($dom, $expiresSpecs);

        $serverSession = Logins::serverSession($serverId, Config::getServers());
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);
        $roles = $db?->getRoles() ?? [];
        $membersSelectionValues = [];

        foreach ($roles as $dbRole) {
            $membersSelectionValues[] = $dbRole->Name;
        }

        $currentMembersOf = [];
        $currentMembers = [];
        $currentAdminMembers = [];

        if (!is_null($role) && !is_null($db)) {
            $currentMembersOf = $db->getMemberOf($role->Name);
            $currentMembers = $db->getMembers($role->Name);
            $currentAdminMembers = $db->getMembers($role->Name, true);
        }

        $memberOfSpecs = [
            'id' => 'memberof[]',
            'label-text' => _('Member of'),
            'value' => [
                'selected-values' => $currentMembersOf,
                'values' => $membersSelectionValues,
            ],
        ];
        $trMemberOf = WebsiteComponents::buildTableRowForSelectionFormular($dom, $memberOfSpecs);

        $membersSpecs = [
            'id' => 'members[]',
            'label-text' => _('Members'),
            'value' => [
                'selected-values' => $currentMembers,
                'values' => $membersSelectionValues,
            ],
        ];
        $trMembers = WebsiteComponents::buildTableRowForSelectionFormular($dom, $membersSpecs);

        $adminMembersSpecs = [
            'id' => 'adminmembers[]',
            'label-text' => _('Admin members'),
            'value' => [
                'selected-values' => $currentAdminMembers,
                'values' => $membersSelectionValues,
            ],
        ];
        $trAdminMembers = WebsiteComponents::buildTableRowForSelectionFormular($dom, $adminMembersSpecs);

        $tBody->appendChild($trName);
        $tBody->appendChild($trPassword);
        $tBody->appendChild($trConfirm);
        $tBody->appendChild($trSuperuser);
        $tBody->appendChild($trCreateDb);
        $tBody->appendChild($trCreateRole);
        $tBody->appendChild($trInherits);
        $tBody->appendChild($trCanLogin);
        $tBody->appendChild($trMaxConnections);
        $tBody->appendChild($trExpires);
        $tBody->appendChild($trMemberOf);
        $tBody->appendChild($trMembers);
        $tBody->appendChild($trAdminMembers);

        $table->appendChild($tBody);

        return $table;
    }

    private function handlePostRequest(): void
    {
        $roleFromForm = DTORole::createFromFormRequest();

        if ($roleFromForm->Name === '') {
            $this->message = _('You must give a name for the role.');

            return;
        }

        $formPassword = RequestParameter::getString(Role::FORM_ID_PASSWORD) ?? '';
        $formConfirm = RequestParameter::getString(Role::FORM_ID_PASSWORD_CONFIRMATION) ?? '';

        if ($formPassword !== $formConfirm) {
            $this->message = _('Password does not match confirmation.');

            return;
        }

        $formMemberOf = RequestParameter::getArray('memberof') ?? [];
        $formMembers = RequestParameter::getArray('members') ?? [];
        $formAdminMembers = RequestParameter::getArray('adminmembers') ?? [];

        $memberOf = [];
        $members = [];
        $adminMembers = [];

        foreach ($formMemberOf as $member) {
            if (is_string($member)) {
                $memberOf[] = $member;
            }
        }

        foreach ($formMembers as $member) {
            if (is_string($member)) {
                $members[] = $member;
            }
        }

        foreach ($formAdminMembers as $member) {
            if (is_string($member)) {
                $adminMembers[] = $member;
            }
        }

        $serverId = RequestParameter::getString('server') ?? '';
        $serverSession = Logins::serverSession($serverId, Config::getServers());
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);

        if (is_null($db)) {
            return;
        }

        try {
            $db->createRole(
                role: $roleFromForm,
                password: $formPassword,
                memberOf: $memberOf,
                members: $members,
                adminMembers: $adminMembers,
            );

            if (!headers_sent()) {
                $redirectUrl = 'roles.php';
                $redirectUrlParams = [
                    'message' => _('Role created.'),
                    'server' => $serverId,
                    'subject' => 'server',
                ];
                header('Location: ' . $redirectUrl . '?' . http_build_query($redirectUrlParams));
                die;
            }
        } catch (\PDOException $e) {
            $this->message = _('Create role failed.');
            $this->pdoException = $e;
        } catch (\Throwable) {
            $this->message = _('Create role failed.');
        }
    }
}
