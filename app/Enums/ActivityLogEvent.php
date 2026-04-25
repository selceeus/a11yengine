<?php

namespace App\Enums;

enum ActivityLogEvent: string
{
    // ── Authentication ──────────────────────────────────────────────────────
    case UserLogin = 'user.login';
    case UserLogout = 'user.logout';
    case UserPasswordChanged = 'user.password_changed';
    case UserTwoFactorEnabled = 'user.2fa_enabled';
    case UserTwoFactorDisabled = 'user.2fa_disabled';

    // ── User management ────────────────────────────────────────────────────
    case UserInvited = 'user.invited';
    case UserRoleChanged = 'user.role_changed';

    // ── API keys ────────────────────────────────────────────────────────────
    case ApiKeyCreated = 'api_key.created';
    case ApiKeyRevoked = 'api_key.revoked';
    case ApiKeyUsed = 'api_key.used';
    case ApiKeyExpiringSoon = 'api_key.expiring_soon';
    case ApiKeyAutoRevoked = 'api_key.auto_revoked';

    // ── Scans ───────────────────────────────────────────────────────────────
    case ScanStarted = 'scan.started';
    case ScanCompleted = 'scan.completed';
    case ScanFailed = 'scan.failed';

    // ── Issues ──────────────────────────────────────────────────────────────
    case IssueStatusChanged = 'issue.status_changed';
    case IssueAssigned = 'issue.assigned';
    case IssueCommentAdded = 'issue.comment_added';

    // ── Audits ──────────────────────────────────────────────────────────────
    case AuditGenerated = 'audit.generated';

    // ── Properties / Organisations ──────────────────────────────────────────
    case PropertyCreated = 'property.created';
    case PropertyUpdated = 'property.updated';
    case OrganizationCreated = 'organization.created';
    case OrganizationUpdated = 'organization.updated';

    // ── Security ────────────────────────────────────────────────────────────
    case UserLoginFailed = 'user.login_failed';

    // ── Access reviews ──────────────────────────────────────────────────────
    case AccessReviewStarted = 'access_review.started';
    case AccessReviewCompleted = 'access_review.completed';
    case UserAccessConfirmed = 'access_review.user_confirmed';
    case UserAccessRevoked = 'access_review.user_revoked';

    // ── System ──────────────────────────────────────────────────────────────
    case ActivityLogPruned = 'activity_log.pruned';

    public function label(): string
    {
        return match ($this) {
            self::UserLogin => 'Logged in',
            self::UserLogout => 'Logged out',
            self::UserPasswordChanged => 'Changed password',
            self::UserTwoFactorEnabled => 'Enabled two-factor authentication',
            self::UserTwoFactorDisabled => 'Disabled two-factor authentication',
            self::UserInvited => 'Invited a new team member',
            self::UserRoleChanged => 'Role changed',
            self::ApiKeyCreated => 'Created API key',
            self::ApiKeyRevoked => 'Revoked API key',
            self::ApiKeyUsed => 'API key used',
            self::ScanStarted => 'Scan started',
            self::ScanCompleted => 'Scan completed',
            self::ScanFailed => 'Scan failed',
            self::IssueStatusChanged => 'Issue status changed',
            self::IssueAssigned => 'Issue assigned',
            self::IssueCommentAdded => 'Comment added to issue',
            self::AuditGenerated => 'Audit generated',
            self::PropertyCreated => 'Property created',
            self::PropertyUpdated => 'Property updated',
            self::OrganizationCreated => 'Organisation created',
            self::OrganizationUpdated => 'Organisation updated',
            self::UserLoginFailed => 'Failed login attempt',
            self::AccessReviewStarted => 'Access review started',
            self::AccessReviewCompleted => 'Access review completed',
            self::UserAccessConfirmed => 'User access confirmed',
            self::UserAccessRevoked => 'User access revoked',
            self::ApiKeyExpiringSoon => 'API key expiring soon',
            self::ApiKeyAutoRevoked => 'API key auto-revoked',
            self::ActivityLogPruned => 'Activity log pruned',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::UserLogin,
            self::UserLogout,
            self::UserPasswordChanged,
            self::UserTwoFactorEnabled,
            self::UserTwoFactorDisabled => 'authentication',

            self::UserInvited,
            self::UserRoleChanged => 'team',

            self::ApiKeyCreated,
            self::ApiKeyRevoked,
            self::ApiKeyUsed,
            self::ApiKeyExpiringSoon,
            self::ApiKeyAutoRevoked => 'api',

            self::ScanStarted,
            self::ScanCompleted,
            self::ScanFailed => 'scan',

            self::IssueStatusChanged,
            self::IssueAssigned,
            self::IssueCommentAdded => 'issue',

            self::AuditGenerated => 'audit',

            self::PropertyCreated,
            self::PropertyUpdated,
            self::OrganizationCreated,
            self::OrganizationUpdated => 'settings',

            self::UserLoginFailed => 'authentication',

            self::AccessReviewStarted,
            self::AccessReviewCompleted,
            self::UserAccessConfirmed,
            self::UserAccessRevoked => 'team',

            self::ActivityLogPruned => 'system',
        };
    }
}
