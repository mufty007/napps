<?php

namespace App\Support\Authorization;

use App\Enums\Role;

/**
 * What each role may do (PRD: Users and roles). *Where* they may do it comes
 * from their role assignment's scope, enforced by global scopes and policies.
 *
 * Permission names are "{module}.{resource}.{action}". Check them with
 * Gate/@can, e.g. @can('fees.invoices.manage').
 */
final class PermissionMatrix
{
    /**
     * @return array<string, string> permission => description
     */
    public static function permissions(): array
    {
        return [
            // Platform & org
            'platform.admin' => 'Use the secretariat console',
            'platform.audit.view' => 'View the audit log',
            'org.view' => 'View the state/chapter/school tree',
            'org.manage' => 'Create and edit states, chapters and schools',
            'roles.assign' => 'Grant and revoke roles',
            'staff.manage' => 'Invite school staff and set their access',
            'reports.view' => 'View dashboards and reports for own scope',

            // Membership & dues
            'membership.apply' => 'Apply for NAPPS membership',
            'membership.applications.verify' => 'Verify school applications (chapter)',
            'membership.applications.approve' => 'Approve school applications (state)',
            'membership.dues.configure' => 'Set dues and levies',
            'membership.dues.pay' => 'Pay membership dues',
            'membership.dues.record' => 'Record dues paid offline (cash, transfer, teller)',
            'membership.schools.suspend' => 'Suspend and reinstate member schools',

            // Fees & collections
            'fees.structures.manage' => 'Set fee structures',
            'fees.invoices.manage' => 'Raise and send invoices',
            'fees.payments.record' => 'Record cash and teller payments',
            'fees.arrears.view' => 'See debtors and arrears',
            'fees.pay' => 'Pay school fees (parent)',

            // Records & retention
            'records.students.manage' => 'Enrol students and keep records',
            'records.staff.manage' => 'Keep staff records',
            'records.transfers.manage' => 'Request and answer transfer clearance',
            'records.debtor-check' => 'Run the consent-gated cross-school debtor check',

            // Debt claims
            'claims.open' => 'Escalate an overdue account to a claim',
            'claims.mediate' => 'Run chapter mediation',
            'claims.escalated.view' => 'See escalated claims in scope',
            'claims.cases.handle' => 'Work cases assigned by the legal panel',

            // Leases
            'leases.manage' => 'Manage the school lease, vault and disputes',
            'leases.disputes.resolve' => 'Help resolve lease disputes',

            // Surveys, templates, announcements & events
            'surveys.issue' => 'Create and send surveys',
            'surveys.respond' => 'Answer surveys',
            'templates.publish' => 'Publish templates to the library',
            'templates.use' => 'Fill and export templates',
            'announcements.send' => 'Send announcements and circulars',
            'events.manage' => 'Run events, meetings and elections',

            // Community
            'community.access' => 'Use the members-only community',
            'community.moderate' => 'Moderate community content in scope',
            'community.votes.run' => 'Run formal votes',
        ];
    }

    /**
     * @return array<string, list<string>> role value => permissions
     */
    public static function matrix(): array
    {
        $executive = [
            'org.view', 'reports.view', 'surveys.issue', 'announcements.send', 'events.manage',
            'community.access', 'community.votes.run', 'templates.use', 'claims.escalated.view',
            'membership.dues.configure',
        ];

        return [
            Role::Secretariat->value => array_keys(self::permissions()),

            Role::NationalExecutive->value => [...$executive, 'templates.publish', 'membership.schools.suspend'],

            Role::StateExecutive->value => [
                ...$executive, 'membership.applications.approve', 'membership.dues.record', 'membership.schools.suspend',
                'leases.disputes.resolve',
            ],

            Role::ChapterExecutive->value => [
                ...$executive, 'membership.applications.verify', 'membership.dues.record', 'claims.mediate',
                'leases.disputes.resolve',
            ],

            Role::Proprietor->value => [
                'membership.apply', 'membership.dues.pay', 'staff.manage', 'reports.view',
                'fees.structures.manage', 'fees.invoices.manage', 'fees.payments.record', 'fees.arrears.view',
                'records.students.manage', 'records.staff.manage', 'records.transfers.manage', 'records.debtor-check',
                'claims.open', 'leases.manage', 'surveys.respond', 'templates.use', 'community.access',
            ],

            Role::Bursar->value => [
                'fees.invoices.manage', 'fees.payments.record', 'fees.arrears.view',
                'records.students.manage', 'records.transfers.manage', 'records.debtor-check',
                'templates.use', 'community.access',
            ],

            Role::Staff->value => ['records.students.manage', 'community.access'],

            Role::Parent->value => ['fees.pay'],

            Role::LegalPanel->value => ['claims.cases.handle', 'templates.use', 'community.access'],

            Role::Moderator->value => ['community.access', 'community.moderate'],
        ];
    }
}
