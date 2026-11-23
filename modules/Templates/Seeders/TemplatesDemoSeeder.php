<?php

namespace Modules\Templates\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Templates\Models\DocumentTemplate;
use Modules\Templates\Services\TemplateService;

/**
 * Starter library for private schools: five common documents, published and
 * marked as reviewed so the demo works end to end. Real deployments should have
 * a lawyer re-check the wording (editing a template clears the review).
 */
class TemplatesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->where('phone', '+2348000000000')->first()
            ?? User::query()->where('phone', '+2348000000001')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $author) {
            return;
        }

        $service = app(TemplateService::class);

        foreach ($this->templates() as $slug => $template) {
            DocumentTemplate::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'category' => $template['category'],
                    'body_html' => $template['body'],
                    'placeholders' => $service->extractPlaceholders($template['body']),
                    'version' => 1,
                    'legally_reviewed_at' => now(),
                    'reviewed_by' => $author->id,
                    'published_at' => now(),
                    'created_by' => $author->id,
                ],
            );
        }
    }

    /**
     * @return array<string, array{name: string, description: string, category: string, body: string}>
     */
    private function templates(): array
    {
        return [
            'demand_letter' => [
                'name' => 'Demand letter for unpaid school fees',
                'description' => 'Formal notice to a parent or guardian with outstanding fees, giving a deadline before the claim goes to chapter mediation.',
                'category' => 'legal',
                'body' => <<<'HTML'
<p>{{school_name}}<br>{{school_address}}</p>
<p>{{date}}</p>
<p>{{debtor_name}}<br>{{debtor_address}}</p>
<p>Dear {{debtor_name}},</p>
<h1>Demand for payment of outstanding school fees</h1>
<p>We write on behalf of {{school_name}} regarding the school fees owed for your ward, <strong>{{pupil_name}}</strong> ({{class_name}}), for {{term_session}}.</p>
<p>Our records show an outstanding balance of <strong>{{amount}}</strong> ({{amount_in_words}}). Despite our earlier reminders, this sum remains unpaid.</p>
<p>You are required to pay the full amount on or before <strong>{{due_date}}</strong>, by bank transfer or at the school bursary. Please quote your ward's name on every payment and send the proof of payment to the school office.</p>
<p>If we do not receive payment or hear from you by that date, the school will have no option but to refer the matter to the chapter mediation panel of the National Association of Proprietors of Private Schools (NAPPS) and, where necessary, to pursue recovery through the courts. Any court fees and legal costs allowed by law may then be claimed from you.</p>
<p>We would much rather resolve this amicably. If you are facing difficulty, please contact the school on {{school_phone}} within seven days to agree a written payment plan.</p>
<p>Yours faithfully,</p>
<table class="signature"><tr><td>______________________<br>{{signatory_name}}<br>{{signatory_title}}<br>For {{school_name}}</td><td></td></tr></table>
HTML,
            ],

            'fee_agreement' => [
                'name' => 'School fees agreement',
                'description' => 'Agreement between a school and a parent or guardian on fees, payment dates and what happens if payments are missed.',
                'category' => 'fees',
                'body' => <<<'HTML'
<h1>School fees agreement</h1>
<p>This agreement is made on {{date}} between:</p>
<ol>
<li><strong>{{school_name}}</strong> of {{school_address}} ("the School"); and</li>
<li><strong>{{parent_name}}</strong> of {{parent_address}}, telephone {{parent_phone}} ("the Parent").</li>
</ol>
<h2>1. The pupil</h2>
<p>The School agrees to admit and teach <strong>{{pupil_name}}</strong> in {{class_name}} for the {{session}} academic session.</p>
<h2>2. Fees</h2>
<table border="1">
<tr><td>Tuition</td><td class="right">{{tuition_fee}}</td></tr>
<tr><td>Other charges (uniform, books, levies)</td><td class="right">{{other_fees}}</td></tr>
<tr><td><strong>Total payable for the session</strong></td><td class="right"><strong>{{total_amount}}</strong></td></tr>
</table>
<h2>3. Payment terms</h2>
<p>{{payment_terms}}</p>
<h2>4. Late and missed payments</h2>
<p>The Parent understands that fees are due on the agreed dates. If any amount is unpaid for 30 days, the School may issue a written reminder; if it remains unpaid for 90 days, the School may refer the debt to NAPPS chapter mediation and pursue recovery by lawful means.</p>
<h2>5. Withdrawal</h2>
<p>The Parent must give the School at least one term's written notice before withdrawing the pupil. Fees already paid for a term in progress are not refundable unless the School agrees otherwise in writing.</p>
<h2>6. General</h2>
<p>This agreement is governed by the laws of the Federal Republic of Nigeria. Any change must be in writing and signed by both parties.</p>
<table class="signature"><tr>
<td>______________________<br>{{signatory_name}}<br>{{signatory_title}}, for {{school_name}}</td>
<td>______________________<br>{{parent_name}}<br>Parent / Guardian</td>
</tr></table>
HTML,
            ],

            'model_lease' => [
                'name' => 'Model school premises lease',
                'description' => 'Lease of land or buildings for a school: rent, term, deposit, repairs and renewal, in plain English.',
                'category' => 'leases',
                'body' => <<<'HTML'
<h1>Lease of school premises</h1>
<p>This lease is made on {{date}} between:</p>
<ol>
<li><strong>{{landlord_name}}</strong> of {{landlord_address}} ("the Landlord"); and</li>
<li><strong>{{tenant_name}}</strong>, proprietor of {{school_name}} ("the Tenant").</li>
</ol>
<h2>1. The premises</h2>
<p>The Landlord lets to the Tenant the land and buildings at {{premises_address}} ("the Premises") for use as a private school only.</p>
<h2>2. Term</h2>
<p>The lease runs for {{lease_term}}, starting on {{commencement_date}}. The Tenant may renew it for a further term by written notice at least six months before it ends, on terms to be agreed in good faith.</p>
<h2>3. Rent and deposit</h2>
<p>The Tenant shall pay an annual rent of <strong>{{annual_rent}}</strong>, in advance, by bank transfer to the account the Landlord names in writing. The Tenant has paid a refundable security deposit of <strong>{{security_deposit}}</strong>, to be returned at the end of the lease less any sum properly due for damage or unpaid rent.</p>
<h2>4. Repairs and use</h2>
<ul>
<li>The Landlord is responsible for the roof, main walls, foundations and any defect that existed before the lease.</li>
<li>The Tenant keeps the inside of the buildings, compound and furniture in good order and pays for water, electricity and refuse.</li>
<li>The Tenant may not sublet or change the use of the Premises without the Landlord's written consent.</li>
</ul>
<h2>5. Safety and approvals</h2>
<p>The Tenant shall keep the Premises in line with the state ministry of education's requirements and fire and safety rules. The Landlord agrees to sign any document needed to obtain or renew the school's approval.</p>
<h2>6. Ending the lease</h2>
<p>Either party may end the lease for a serious breach that is not put right within 30 days of written notice. The Tenant must give pupils and parents reasonable time to leave in an orderly way at the end of a term.</p>
<h2>7. Disputes</h2>
<p>The parties will first try to settle any dispute through the NAPPS chapter, and only then through the courts of the state where the Premises are found.</p>
<table class="signature"><tr>
<td>______________________<br>{{landlord_name}}<br>Landlord</td>
<td>______________________<br>{{tenant_name}}<br>Tenant, for {{school_name}}</td>
</tr></table>
<p>Witness: ______________________ &nbsp; Name: ______________________ &nbsp; Date: ____________</p>
HTML,
            ],

            'transfer_clearance' => [
                'name' => 'Pupil transfer clearance certificate',
                'description' => 'Confirms a pupil may move to another school, and records any fees still owed, so receiving schools can check before admission.',
                'category' => 'records',
                'body' => <<<'HTML'
<p>{{school_name}}<br>{{school_address}}</p>
<h1>Transfer clearance certificate</h1>
<p>Date: {{date}}</p>
<p>This is to certify that <strong>{{pupil_name}}</strong>, admission number <strong>{{admission_number}}</strong>, is a pupil of {{school_name}} in {{class_name}}.</p>
<p>The pupil is leaving to join <strong>{{receiving_school}}</strong>. Reason for transfer: {{reason}}</p>
<h2>Clearance status</h2>
<table border="1">
<tr><td>Outstanding school fees</td><td class="right">{{outstanding_balance}}</td></tr>
<tr><td>Library books and school property returned</td><td>Yes / No (delete one)</td></tr>
<tr><td>Academic records released to parent</td><td>Yes / No (delete one)</td></tr>
</table>
<p>If a balance is shown above, the parent {{parent_name}} remains responsible for it. This certificate does not cancel the debt and the school may record it on the NAPPS debtor check.</p>
<p>Issued in good faith on request of the parent or guardian.</p>
<table class="signature"><tr><td>______________________<br>{{signatory_name}}<br>{{signatory_title}}<br>School stamp</td><td></td></tr></table>
HTML,
            ],

            'exit_form' => [
                'name' => 'Pupil withdrawal and exit form',
                'description' => 'Checklist signed by the parent when a pupil leaves the school, covering notice, property and final fees.',
                'category' => 'membership',
                'body' => <<<'HTML'
<p>{{school_name}}</p>
<h1>Pupil withdrawal and exit form</h1>
<p>Date: {{date}}</p>
<table border="1">
<tr><td>Pupil's name</td><td>{{pupil_name}}</td></tr>
<tr><td>Admission number</td><td>{{admission_number}}</td></tr>
<tr><td>Class</td><td>{{class_name}}</td></tr>
<tr><td>Parent / guardian</td><td>{{parent_name}} ({{parent_phone}})</td></tr>
<tr><td>Last day in school</td><td>{{exit_date}}</td></tr>
<tr><td>Reason for leaving</td><td>{{reason}}</td></tr>
<tr><td>Fees still owed</td><td>{{outstanding_balance}}</td></tr>
</table>
<h2>Exit checklist</h2>
<ul>
<li>Written notice given to the school: Yes / No</li>
<li>Textbooks, library books and equipment returned: Yes / No</li>
<li>Fees settled or a payment plan agreed in writing: Yes / No</li>
<li>Report cards and records collected by the parent: Yes / No</li>
</ul>
<p>I confirm the details above are correct and that I understand any unpaid fees remain payable to the school.</p>
<table class="signature"><tr>
<td>______________________<br>{{parent_name}}<br>Parent / Guardian</td>
<td>______________________<br>{{signatory_name}}<br>{{signatory_title}}, for {{school_name}}</td>
</tr></table>
HTML,
            ],
        ];
    }
}
