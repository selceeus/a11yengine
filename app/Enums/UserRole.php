<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperUser = 'super_user';
    case AgencyAdmin = 'agency_admin';
    case OrgAdmin = 'org_admin';
    case PropAdmin = 'prop_admin';
    case Editor = 'editor';
    case Viewer = 'viewer';
}
