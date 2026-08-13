<?php

declare(strict_types=1);

/*
 * Generates one local HTML audit page per diagram family, focused on label
 * placement, label length, legends, values, and relationship text.
 *
 * Output is written to examples/output/label-polish/.
 */

use Atelier\Diagram\ClassDiagram\ClassDiagramBuilder;
use Atelier\Diagram\Diagram;
use Atelier\Diagram\Er\ErDiagramBuilder;
use Atelier\Diagram\Flow\FlowchartBuilder;
use Atelier\Diagram\Git\GitGraphBuilder;
use Atelier\Diagram\Kanban\KanbanDiagramBuilder;
use Atelier\Diagram\Mindmap\MindmapDiagramBuilder;
use Atelier\Diagram\Model\Direction;
use Atelier\Diagram\Sequence\MessageArrow;
use Atelier\Diagram\Sequence\SequenceBlockBranch;
use Atelier\Diagram\Sequence\SequenceBlockKind;
use Atelier\Diagram\Sequence\SequenceDiagramBuilder;
use Atelier\Diagram\State\StateDiagramBuilder;
use Atelier\Diagram\Theme\Theme;
use Atelier\Diagram\Timeline\TimelineDiagramBuilder;
use Atelier\Diagram\Venn\VennDiagramBuilder;

require dirname(__DIR__).'/vendor/autoload.php';

final readonly class LabelPolishCase
{
    /**
     * @param list<string> $checks
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $intent,
        public Diagram $diagram,
        public string $source,
        public array $checks,
    ) {
    }
}

final readonly class LabelPolishPage
{
    /**
     * @param list<LabelPolishCase> $cases
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public array $cases,
    ) {
    }
}

$outputDir = __DIR__.'/output/label-polish';
ensureDirectory($outputDir);

$pages = labelPolishPages();
foreach ($pages as $page) {
    foreach ($page->cases as $case) {
        writeFile(
            $outputDir.'/'.$page->slug.'-'.$case->slug.'.svg',
            $case->diagram->toSvg(Theme::default()),
        );
    }

    writeFile($outputDir.'/'.$page->slug.'.html', renderLabelPolishPage($page, $pages));
    echo 'Wrote '.$outputDir.'/'.$page->slug.'.html'.\PHP_EOL;
}

writeFile($outputDir.'/index.html', renderLabelPolishIndex($pages));
echo 'Wrote '.$outputDir.'/index.html'.\PHP_EOL;

/**
 * @return list<LabelPolishPage>
 */
function labelPolishPages(): array
{
    return [
        new LabelPolishPage(
            'flowchart',
            'Flowchart Labels',
            'Arrow labels, subgraph labels, and long node labels across ranked layouts.',
            [
                new LabelPolishCase(
                    'compact',
                    'Compact edge vocabulary',
                    'Short verbs should sit close to the route without feeling like extra nodes.',
                    Diagram::of((new FlowchartBuilder())
                        ->direction(Direction::TopToBottom)
                        ->title('Short checkout')
                        ->node('Cart', 'Cart')
                        ->node('Pay', 'Pay')
                        ->node('Ship', 'Ship')
                        ->edge('Cart', 'Pay', 'pay')
                        ->edge('Pay', 'Ship', 'ok')
                        ->subgraph('checkout', 'Checkout', ['Cart', 'Pay', 'Ship'])
                        ->build()),
                    <<<'SOURCE'
                    flowchart TD
                        title Short checkout
                        subgraph checkout [Checkout]
                            Cart[Cart]
                            Pay[Pay]
                            Ship[Ship]
                        end
                        Cart -->|pay| Pay
                        Pay -->|ok| Ship
                    SOURCE,
                    ['Label halos should be just visible, not boxy.', 'Subgraph title should not collide with the first node.', 'Short labels should stay centered on their route.'],
                ),
                new LabelPolishCase(
                    'long-crossing',
                    'Long labels around a decision',
                    'Longer phrases should remain readable while avoiding adjacent boxes and routed elbows.',
                    Diagram::of((new FlowchartBuilder())
                        ->direction(Direction::TopToBottom)
                        ->title('Review routing')
                        ->node('Start', 'Start')
                        ->node('Policy', 'Policy validation')
                        ->node('Fraud', 'Fraud review queue')
                        ->node('Manual', 'Manual approval')
                        ->node('Done', 'Completed')
                        ->edge('Start', 'Policy', 'submit order')
                        ->edge('Policy', 'Fraud', 'requires additional verification')
                        ->edge('Policy', 'Done', 'eligible for automatic fulfillment')
                        ->edge('Fraud', 'Manual', 'analyst asks for supporting evidence')
                        ->edge('Manual', 'Done', 'approved after review')
                        ->subgraph('risk', 'Risk and compliance checks', ['Policy', 'Fraud', 'Manual'])
                        ->build()),
                    <<<'SOURCE'
                    flowchart TD
                        title Review routing
                        subgraph risk [Risk and compliance checks]
                            Policy[Policy validation]
                            Fraud[Fraud review queue]
                            Manual[Manual approval]
                        end
                        Start -->|submit order| Policy
                        Policy -->|requires additional verification| Fraud
                        Policy -->|eligible for automatic fulfillment| Done
                        Fraud -->|analyst asks for supporting evidence| Manual
                        Manual -->|approved after review| Done
                    SOURCE,
                    ['Long edge labels should not read as node labels.', 'Label backgrounds should separate text from arrows without hiding too much route.', 'Dense labels should not push the visual weight away from the main flow.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'block',
            'Block Diagram Labels',
            'Block ids, block names, group captions, and relationship labels.',
            [
                new LabelPolishCase(
                    'basic',
                    'Short block relationships',
                    'Simple kernel relationships should remain compact and visually calm.',
                    Diagram::of(Diagram::block()
                        ->title('Kernel blocks')
                        ->block('Solver', 'Solver')
                        ->block('Grid', 'Grid')
                        ->block('Text', 'Text')
                        ->relationship('Solver', 'Grid', 'solves')
                        ->relationship('Solver', 'Text', 'measures')
                        ->build()),
                    <<<'SOURCE'
                    block
                        title Kernel blocks
                        block Solver [Solver]
                        block Grid [Grid]
                        block Text [Text]
                        Solver -> Grid : solves
                        Solver -> Text : measures
                    SOURCE,
                    ['Relationship labels should not crowd block ids.', 'Short labels should not look oversized versus block titles.'],
                ),
                new LabelPolishCase(
                    'grouped-long',
                    'Grouped blocks with long relationships',
                    'Long labels should stay attached to their route while group captions remain legible.',
                    Diagram::of(Diagram::block()
                        ->title('Rendering pipeline')
                        ->beginGroup('Input', 'Input adapters and parser boundary')
                            ->block('Parser', 'MermaidParser')
                            ->block('Builder', 'Typed builders')
                        ->endGroup()
                        ->beginGroup('Output', 'Scene to SVG output')
                            ->block('Layout', 'Layout registry')
                            ->block('Renderer', 'SVG renderer')
                        ->endGroup()
                        ->relationship('Parser', 'Layout', 'produces a validated diagram model')
                        ->relationship('Builder', 'Layout', 'bypasses parsing with typed data')
                        ->relationship('Layout', 'Renderer', 'emits positioned scene nodes')
                        ->build()),
                    <<<'SOURCE'
                    block
                        title Rendering pipeline
                        group Input [Input adapters and parser boundary]
                            block Parser [MermaidParser]
                            block Builder [Typed builders]
                        end
                        group Output [Scene to SVG output]
                            block Layout [Layout registry]
                            block Renderer [SVG renderer]
                        end
                        Parser -> Layout : produces a validated diagram model
                        Builder -> Layout : bypasses parsing with typed data
                        Layout -> Renderer : emits positioned scene nodes
                    SOURCE,
                    ['Long group captions need enough inset from borders.', 'Relationship labels should not sit on top of group borders.', 'The label halo should work over both plain canvas and group backgrounds.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'git',
            'Git Graph Labels',
            'Branch lane labels, tag badges, commit ids, and the automatic legend.',
            [
                new LabelPolishCase(
                    'legend',
                    'Branch legend lengths',
                    'Branch labels in lanes and legends should stay aligned as names get longer.',
                    Diagram::of((new GitGraphBuilder())
                        ->title('Release branches')
                        ->commit('base001')
                        ->branch('feature-auth')
                        ->commit('auth001', 'auth-beta')
                        ->checkout('main')
                        ->branch('hotfix-payment-timeout')
                        ->commit('pay0001', 'urgent-fix')
                        ->checkout('main')
                        ->merge('feature-auth')
                        ->merge('hotfix-payment-timeout')
                        ->commit('rel0001', 'v1.2.0')
                        ->build()),
                    <<<'SOURCE'
                    gitGraph LR:
                        commit id: "base001"
                        branch feature-auth
                        commit id: "auth001" tag: "auth-beta"
                        checkout main
                        branch hotfix-payment-timeout
                        commit id: "pay0001" tag: "urgent-fix"
                        checkout main
                        merge feature-auth
                        merge hotfix-payment-timeout
                        commit id: "rel0001" tag: "v1.2.0"
                    SOURCE,
                    ['Long branch names should not dominate the left rail.', 'Legend rows should keep swatches and text optically aligned.', 'Tag badges should not collide with nearby commits.'],
                ),
                new LabelPolishCase(
                    'vertical',
                    'Vertical lane labels',
                    'Vertical git graphs stress branch labels differently from horizontal lanes.',
                    Diagram::of((new GitGraphBuilder())
                        ->direction(Direction::TopToBottom)
                        ->title('Mobile release train')
                        ->commit('start')
                        ->branch('ios-gesture-polish')
                        ->commit('ios001', 'tap-copy')
                        ->checkout('main')
                        ->branch('android-reader-mode')
                        ->commit('and001')
                        ->checkout('main')
                        ->merge('ios-gesture-polish')
                        ->merge('android-reader-mode')
                        ->commit('shipit', 'mobile-v3')
                        ->build()),
                    <<<'SOURCE'
                    gitGraph TB:
                        commit id: "start"
                        branch ios-gesture-polish
                        commit id: "ios001" tag: "tap-copy"
                        checkout main
                        branch android-reader-mode
                        commit id: "and001"
                        checkout main
                        merge ios-gesture-polish
                        merge android-reader-mode
                        commit id: "shipit" tag: "mobile-v3"
                    SOURCE,
                    ['Branch labels should remain readable above lanes.', 'Legend should feel secondary to the graph.', 'Tags should avoid route elbows.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'state',
            'State Diagram Labels',
            'Transition labels, state aliases, self-loops, and final markers.',
            [
                new LabelPolishCase(
                    'short',
                    'Short lifecycle labels',
                    'Short transition verbs should read quickly and stay close to arrows.',
                    Diagram::of((new StateDiagramBuilder())
                        ->direction(Direction::TopToBottom)
                        ->title('Draft state')
                        ->initial('Draft')
                        ->state('Review', 'Review')
                        ->transition('Draft', 'Review', 'submit')
                        ->transition('Review', 'Draft', 'revise')
                        ->transition('Review', 'Approved', 'approve')
                        ->final('Approved')
                        ->build()),
                    <<<'SOURCE'
                    stateDiagram-v2
                        direction TB
                        [*] --> Draft
                        Draft --> Review : submit
                        Review --> Draft : revise
                        Review --> Approved : approve
                        Approved --> [*]
                    SOURCE,
                    ['Transition labels should not compete with state labels.', 'Back-edge text should stay readable on the return route.'],
                ),
                new LabelPolishCase(
                    'long-self-loop',
                    'Long self-loop and rejection text',
                    'Long labels should not make loops or diagonal routes feel accidental.',
                    Diagram::of((new StateDiagramBuilder())
                        ->direction(Direction::LeftToRight)
                        ->title('Approval state')
                        ->initial('Intake')
                        ->state('Review', 'Manual review in progress')
                        ->state('Rejected', 'Rejected with explanation')
                        ->transition('Intake', 'Review', 'submitted with required documents')
                        ->transition('Review', 'Review', 'request clarifying evidence from customer')
                        ->transition('Review', 'Rejected', 'policy threshold exceeded')
                        ->transition('Rejected', 'Intake', 'customer resubmits corrected package')
                        ->transition('Review', 'Approved', 'approved for fulfillment')
                        ->final('Approved')
                        ->build()),
                    <<<'SOURCE'
                    stateDiagram-v2
                        direction LR
                        state "Manual review in progress" as Review
                        state "Rejected with explanation" as Rejected
                        [*] --> Intake
                        Intake --> Review : submitted with required documents
                        Review --> Review : request clarifying evidence from customer
                        Review --> Rejected : policy threshold exceeded
                        Rejected --> Intake : customer resubmits corrected package
                        Review --> Approved : approved for fulfillment
                        Approved --> [*]
                    SOURCE,
                    ['Self-loop labels need a clear relationship to the loop.', 'Long return labels should avoid state rectangles.', 'Arrow label backgrounds should not obscure terminal markers.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'sequence',
            'Sequence Labels',
            'Participant aliases, message labels, dashed replies, and block captions.',
            [
                new LabelPolishCase(
                    'compact',
                    'Compact messages',
                    'Short request/reply labels should keep the sequence rhythm tight.',
                    Diagram::of((new SequenceDiagramBuilder())
                        ->title('Login check')
                        ->participant('User', 'User')
                        ->participant('App', 'App')
                        ->participant('Api', 'API')
                        ->message('User', 'App', 'Sign in')
                        ->message('App', 'Api', 'Auth')
                        ->message('Api', 'App', 'OK', MessageArrow::Dashed)
                        ->message('App', 'User', 'Dashboard')
                        ->build()),
                    <<<'SOURCE'
                    sequenceDiagram
                        title Login check
                        participant User
                        participant App
                        participant Api as API
                        User->>App: Sign in
                        App->>Api: Auth
                        Api-->>App: OK
                        App->>User: Dashboard
                    SOURCE,
                    ['Message text should sit clearly above arrows.', 'Participant aliases should not crowd lifelines.'],
                ),
                new LabelPolishCase(
                    'long-blocks',
                    'Long messages with block captions',
                    'Long block and message labels should remain legible without flattening the flow.',
                    Diagram::of((new SequenceDiagramBuilder())
                        ->title('Checkout review')
                        ->participant('Customer', 'Returning customer with saved card')
                        ->participant('App', 'Frontend application')
                        ->participant('Api', 'Checkout API')
                        ->participant('Risk', 'Risk scoring service')
                        ->message('Customer', 'App', 'Confirm cart with delivery preferences')
                        ->message('App', 'Api', 'Create order draft and reserve inventory')
                        ->message('Api', 'Risk', 'Score payment and shipping combination')
                        ->message('Risk', 'Api', 'Manual review recommended', MessageArrow::Dashed)
                        ->block(SequenceBlockKind::Alt, 'risk score requires manual review', 2, 4, [
                            new SequenceBlockBranch('risk score requires manual review', 2, 3),
                            new SequenceBlockBranch('score remains under automatic threshold', 4, 4),
                        ])
                        ->message('Api', 'App', 'Show pending review state to customer', MessageArrow::Dashed)
                        ->build()),
                    <<<'SOURCE'
                    sequenceDiagram
                        title Checkout review
                        participant Customer as Returning customer with saved card
                        participant App as Frontend application
                        participant Api as Checkout API
                        participant Risk as Risk scoring service
                        Customer->>App: Confirm cart with delivery preferences
                        App->>Api: Create order draft and reserve inventory
                        alt risk score requires manual review
                        Api->>Risk: Score payment and shipping combination
                        Risk-->>Api: Manual review recommended
                        else score remains under automatic threshold
                        Api-->>App: Show pending review state to customer
                        end
                    SOURCE,
                    ['Long participant labels should remain distinct.', 'Block captions should not collide with message text.', 'Dashed reply labels should have the same optical weight as requests.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'class',
            'Class Diagram Labels',
            'Class names, member rows, and relationship labels.',
            [
                new LabelPolishCase(
                    'basic',
                    'Short class relations',
                    'A tiny model checks baseline relation label placement.',
                    Diagram::of((new ClassDiagramBuilder())
                        ->member('User', '+id int')
                        ->member('User', '+email string')
                        ->member('Order', '+total Money')
                        ->relation('User', 'Order', 'places')
                        ->build()),
                    <<<'SOURCE'
                    classDiagram
                        class User
                        User : +id int
                        User : +email string
                        class Order
                        Order : +total Money
                        User --> Order : places
                    SOURCE,
                    ['Relation labels should not look like member rows.', 'Class names should remain the main labels.'],
                ),
                new LabelPolishCase(
                    'long-members',
                    'Long members and relation phrases',
                    'Long member text and relation text should not collapse the box rhythm.',
                    Diagram::of((new ClassDiagramBuilder())
                        ->member('SubscriptionAccount', '+billingContactEmail string')
                        ->member('SubscriptionAccount', '+nextRenewalWindow DateInterval')
                        ->member('InvoiceAdjustmentPolicy', '+requiresManagerApproval bool')
                        ->member('InvoiceAdjustmentPolicy', '+maximumAutomaticCredit Money')
                        ->member('AuditTrailEntry', '+createdFromSupportConsole bool')
                        ->relation('SubscriptionAccount', 'InvoiceAdjustmentPolicy', 'uses policy during renewal calculation')
                        ->relation('InvoiceAdjustmentPolicy', 'AuditTrailEntry', 'records approval outcome')
                        ->build()),
                    <<<'SOURCE'
                    classDiagram
                        class SubscriptionAccount
                        SubscriptionAccount : +billingContactEmail string
                        SubscriptionAccount : +nextRenewalWindow DateInterval
                        class InvoiceAdjustmentPolicy
                        InvoiceAdjustmentPolicy : +requiresManagerApproval bool
                        InvoiceAdjustmentPolicy : +maximumAutomaticCredit Money
                        class AuditTrailEntry
                        AuditTrailEntry : +createdFromSupportConsole bool
                        SubscriptionAccount --> InvoiceAdjustmentPolicy : uses policy during renewal calculation
                        InvoiceAdjustmentPolicy --> AuditTrailEntry : records approval outcome
                    SOURCE,
                    ['Long class names should not crowd neighboring boxes.', 'Relation labels should avoid class borders.', 'Member rows should remain scan-friendly.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'er',
            'ER Diagram Labels',
            'Entity names, attributes, cardinality markers, and relationship labels.',
            [
                new LabelPolishCase(
                    'compact',
                    'Compact commerce relations',
                    'Short relationship labels should not interfere with cardinality markers.',
                    Diagram::of((new ErDiagramBuilder())
                        ->attribute('CUSTOMER', 'int', 'id')
                        ->attribute('CUSTOMER', 'string', 'email')
                        ->attribute('ORDER', 'int', 'id')
                        ->attribute('ORDER', 'decimal', 'total')
                        ->attribute('PAYMENT', 'int', 'id')
                        ->relationship('CUSTOMER', '||', 'ORDER', 'o{', 'places')
                        ->relationship('ORDER', '||', 'PAYMENT', 'o{', 'paid by')
                        ->build()),
                    <<<'SOURCE'
                    erDiagram
                        CUSTOMER {
                            int id
                            string email
                        }
                        ORDER {
                            int id
                            decimal total
                        }
                        PAYMENT {
                            int id
                        }
                        CUSTOMER ||--o{ ORDER : places
                        ORDER ||--o{ PAYMENT : paid by
                    SOURCE,
                    ['Relationship labels should not cover cardinality text.', 'Entity labels should remain stronger than attributes.'],
                ),
                new LabelPolishCase(
                    'long-relationships',
                    'Long relationship phrases',
                    'Long ER labels should stay readable without hiding relationship semantics.',
                    Diagram::of((new ErDiagramBuilder())
                        ->attribute('SUBSCRIPTION_ACCOUNT', 'uuid', 'id')
                        ->attribute('SUBSCRIPTION_ACCOUNT', 'string', 'billingContactEmail')
                        ->attribute('RENEWAL_POLICY', 'string', 'approvalWorkflowName')
                        ->attribute('RENEWAL_POLICY', 'decimal', 'maximumAutomaticCredit')
                        ->attribute('SUPPORT_CASE', 'string', 'externalReferenceNumber')
                        ->relationship('SUBSCRIPTION_ACCOUNT', '||', 'RENEWAL_POLICY', 'o{', 'evaluates before each renewal')
                        ->relationship('SUPPORT_CASE', 'o{', 'SUBSCRIPTION_ACCOUNT', '||', 'documents customer exception requests')
                        ->build()),
                    <<<'SOURCE'
                    erDiagram
                        SUBSCRIPTION_ACCOUNT {
                            uuid id
                            string billingContactEmail
                        }
                        RENEWAL_POLICY {
                            string approvalWorkflowName
                            decimal maximumAutomaticCredit
                        }
                        SUPPORT_CASE {
                            string externalReferenceNumber
                        }
                        SUBSCRIPTION_ACCOUNT ||--o{ RENEWAL_POLICY : evaluates before each renewal
                        SUPPORT_CASE o{--|| SUBSCRIPTION_ACCOUNT : documents customer exception requests
                    SOURCE,
                    ['Long entity names should not force awkward label placement.', 'Relationship text should remain attached to the line.', 'Cardinality markers should still be identifiable.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'timeline',
            'Timeline Labels',
            'Section titles, event labels, and date/value captions.',
            [
                new LabelPolishCase(
                    'compact',
                    'Short milestones',
                    'Short date labels should align cleanly with event names.',
                    Diagram::of((new TimelineDiagramBuilder())
                        ->title('Launch')
                        ->section('Plan')
                        ->event('Brief', 'Jan')
                        ->event('Prototype', 'Feb')
                        ->section('Ship')
                        ->event('Beta', 'May')
                        ->event('Launch', 'Jun')
                        ->build()),
                    <<<'SOURCE'
                    timeline
                        title Launch
                        section Plan
                            Brief : Jan
                            Prototype : Feb
                        section Ship
                            Beta : May
                            Launch : Jun
                    SOURCE,
                    ['Date/value captions should be visually secondary.', 'Section titles should anchor each row.'],
                ),
                new LabelPolishCase(
                    'long-events',
                    'Long milestone labels',
                    'Long event labels and values should not create cramped rows.',
                    Diagram::of((new TimelineDiagramBuilder())
                        ->title('Enterprise onboarding rollout')
                        ->section('Discovery and contract')
                        ->event('Security questionnaire complete', '2026 Q1')
                        ->event('Data processing addendum signed', 'March approval window')
                        ->section('Migration')
                        ->event('Historical import dry run', 'two-week validation')
                        ->event('Production cutover with support coverage', 'launch weekend')
                        ->build()),
                    <<<'SOURCE'
                    timeline
                        title Enterprise onboarding rollout
                        section Discovery and contract
                            Security questionnaire complete : 2026 Q1
                            Data processing addendum signed : March approval window
                        section Migration
                            Historical import dry run : two-week validation
                            Production cutover with support coverage : launch weekend
                    SOURCE,
                    ['Long event labels should preserve row rhythm.', 'Values should not be mistaken for section titles.', 'Dense labels should remain legible at page scale.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'journey',
            'Journey Labels',
            'Phase labels, task labels, score values, and actor captions.',
            [
                new LabelPolishCase(
                    'compact',
                    'Short journey tasks',
                    'Scores should be visible without overpowering task labels.',
                    Diagram::of(Diagram::journey()
                        ->title('Checkout')
                        ->section('Browse')
                        ->task('Open page', 5, ['Customer'])
                        ->task('Add item', 4, ['Customer'])
                        ->section('Pay')
                        ->task('Enter card', 3, ['Customer'])
                        ->task('Confirm', 5, ['Customer'])
                        ->build()),
                    <<<'SOURCE'
                    journey
                        title Checkout
                        section Browse
                            Open page: 5: Customer
                            Add item: 4: Customer
                        section Pay
                            Enter card: 3: Customer
                            Confirm: 5: Customer
                    SOURCE,
                    ['Score values should read as values, not badges.', 'Actor captions should stay secondary.'],
                ),
                new LabelPolishCase(
                    'long-tasks',
                    'Long tasks and actor names',
                    'Long task names and multiple actors stress the row labels and captions.',
                    Diagram::of(Diagram::journey()
                        ->title('Support-assisted renewal')
                        ->section('Account review')
                        ->task('Compare renewal quote against negotiated contract terms', 2, ['Account manager', 'Customer admin'])
                        ->task('Request finance approval for non-standard discount', 1, ['Finance reviewer'])
                        ->section('Resolution')
                        ->task('Send revised order form with clear next steps', 4, ['Account manager', 'Legal operations'])
                        ->task('Confirm activation date and billing contact', 5, ['Customer admin'])
                        ->build()),
                    <<<'SOURCE'
                    journey
                        title Support-assisted renewal
                        section Account review
                            Compare renewal quote against negotiated contract terms: 2: Account manager, Customer admin
                            Request finance approval for non-standard discount: 1: Finance reviewer
                        section Resolution
                            Send revised order form with clear next steps: 4: Account manager, Legal operations
                            Confirm activation date and billing contact: 5: Customer admin
                    SOURCE,
                    ['Long task labels should not overlap score/value markers.', 'Multiple actors should remain readable.', 'Low and high scores should stay visually comparable.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'requirement',
            'Requirement Labels',
            'Requirement ids, field values, element labels, and relationship kinds.',
            [
                new LabelPolishCase(
                    'compact',
                    'Short requirement fields',
                    'Requirement metadata should remain compact around relationship labels.',
                    Diagram::of(Diagram::requirement()
                        ->requirement('checkout', ['id' => 'REQ-1', 'text' => 'Customer can checkout', 'risk' => 'medium'])
                        ->element('cart', ['type' => 'component'])
                        ->element('payment', ['type' => 'service'])
                        ->relationship('cart', 'satisfies', 'checkout')
                        ->relationship('payment', 'verifies', 'checkout')
                        ->build()),
                    <<<'SOURCE'
                    requirementDiagram
                        requirement checkout {
                            id: REQ-1
                            text: Customer can checkout
                            risk: medium
                        }
                        element cart {
                            type: component
                        }
                        element payment {
                            type: service
                        }
                        cart - satisfies -> checkout
                        payment - verifies -> checkout
                    SOURCE,
                    ['Relationship kind labels should be distinct from field names.', 'Requirement ids should remain easy to scan.'],
                ),
                new LabelPolishCase(
                    'long-fields',
                    'Long fields and relationship kinds',
                    'Long requirement text stresses node sizing and route-label placement.',
                    Diagram::of(Diagram::requirement()
                        ->requirement('audit', [
                            'id' => 'REQ-AUDIT-RETENTION',
                            'text' => 'Every account change must be reconstructable from immutable audit events',
                            'risk' => 'high',
                            'verifymethod' => 'inspection plus automated regression test',
                        ])
                        ->requirement('export', [
                            'id' => 'REQ-EXPORT-PACKAGE',
                            'text' => 'Administrators can export a signed compliance evidence package',
                            'risk' => 'medium',
                        ])
                        ->element('eventStore', ['type' => 'append-only storage component'])
                        ->element('adminConsole', ['type' => 'restricted operator interface'])
                        ->relationship('eventStore', 'satisfies', 'audit')
                        ->relationship('adminConsole', 'verifies', 'export')
                        ->relationship('audit', 'contains', 'export')
                        ->build()),
                    <<<'SOURCE'
                    requirementDiagram
                        requirement audit {
                            id: REQ-AUDIT-RETENTION
                            text: Every account change must be reconstructable from immutable audit events
                            risk: high
                            verifymethod: inspection plus automated regression test
                        }
                        requirement export {
                            id: REQ-EXPORT-PACKAGE
                            text: Administrators can export a signed compliance evidence package
                            risk: medium
                        }
                        element eventStore {
                            type: append-only storage component
                        }
                        element adminConsole {
                            type: restricted operator interface
                        }
                        eventStore - satisfies -> audit
                        adminConsole - verifies -> export
                        audit - contains -> export
                    SOURCE,
                    ['Long field values should not make routes feel detached.', 'Relationship labels should avoid node interiors.', 'Field labels and field values need clear hierarchy.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'kanban',
            'Kanban Labels',
            'Column labels, card ids, and card titles.',
            [
                new LabelPolishCase(
                    'compact',
                    'Short card labels',
                    'Short labels should make a dense board easy to scan.',
                    Diagram::of((new KanbanDiagramBuilder())
                        ->title('Delivery')
                        ->column('todo', 'Todo')
                        ->card('todo', 'A-1', 'Parser')
                        ->card('todo', 'A-2', 'Docs')
                        ->column('doing', 'Doing')
                        ->card('doing', 'B-1', 'Renderer')
                        ->column('done', 'Done')
                        ->card('done', 'C-1', 'Tests')
                        ->build()),
                    <<<'SOURCE'
                    kanban
                        title Delivery
                        todo [Todo]
                            A-1 [Parser]
                            A-2 [Docs]
                        doing [Doing]
                            B-1 [Renderer]
                        done [Done]
                            C-1 [Tests]
                    SOURCE,
                    ['Card ids should be visible but secondary.', 'Column headers should anchor the board.'],
                ),
                new LabelPolishCase(
                    'long-cards',
                    'Long cards and column headers',
                    'Long labels check wrapping/clipping pressure inside card-like shapes.',
                    Diagram::of((new KanbanDiagramBuilder())
                        ->title('Label polish board')
                        ->column('research', 'Research and visual audit')
                        ->card('research', 'LBL-101', 'Compare edge label halo against route color')
                        ->card('research', 'LBL-102', 'Collect long localized captions from product examples')
                        ->column('implementation', 'Implementation candidates')
                        ->card('implementation', 'LBL-201', 'Prototype maximum label width and fallback placement')
                        ->column('verification', 'Verification and screenshots')
                        ->card('verification', 'LBL-301', 'Capture before and after pages for review')
                        ->build()),
                    <<<'SOURCE'
                    kanban
                        title Label polish board
                        research [Research and visual audit]
                            LBL-101 [Compare edge label halo against route color]
                            LBL-102 [Collect long localized captions from product examples]
                        implementation [Implementation candidates]
                            LBL-201 [Prototype maximum label width and fallback placement]
                        verification [Verification and screenshots]
                            LBL-301 [Capture before and after pages for review]
                    SOURCE,
                    ['Long card titles should not overflow their cards.', 'Column headers should still fit in the board rhythm.', 'Ids and labels should have clear visual priority.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'mindmap',
            'Mindmap Labels',
            'Root, branch, and leaf labels across nested levels.',
            [
                new LabelPolishCase(
                    'compact',
                    'Short tree labels',
                    'Compact labels should make the hierarchy obvious.',
                    Diagram::of((new MindmapDiagramBuilder())
                        ->root('Atelier', 'root')
                        ->child('root', 'Layout', 'layout')
                        ->child('layout', 'Grid')
                        ->child('layout', 'Route')
                        ->child('root', 'SVG', 'svg')
                        ->child('svg', 'Scene')
                        ->build()),
                    <<<'SOURCE'
                    mindmap
                      root((Atelier))
                        Layout
                          Grid
                          Route
                        SVG
                          Scene
                    SOURCE,
                    ['Branch labels should be clearly attached to connectors.', 'Root label should dominate without becoming a card.'],
                ),
                new LabelPolishCase(
                    'long-branches',
                    'Long branches',
                    'Long branch and leaf labels stress horizontal spacing.',
                    Diagram::of((new MindmapDiagramBuilder())
                        ->root('Diagram label polish', 'root')
                        ->child('root', 'Route label placement strategy', 'routes')
                        ->child('routes', 'Prefer visually central segment')
                        ->child('routes', 'Avoid node and group boundaries')
                        ->child('root', 'Legend and value presentation', 'legends')
                        ->child('legends', 'Swatches align to text baseline')
                        ->child('legends', 'Long captions remain secondary')
                        ->build()),
                    <<<'SOURCE'
                    mindmap
                      root((Diagram label polish))
                        Route label placement strategy
                          Prefer visually central segment
                          Avoid node and group boundaries
                        Legend and value presentation
                          Swatches align to text baseline
                          Long captions remain secondary
                    SOURCE,
                    ['Long labels should not erase the hierarchy.', 'Sibling spacing should stay balanced.', 'Connectors should still feel connected to labels.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'architecture',
            'Architecture Labels',
            'Group labels, component labels, node kinds, and cross-tier relationship labels.',
            [
                new LabelPolishCase(
                    'compact',
                    'Short platform labels',
                    'Small architecture diagrams should keep relationship labels subtle.',
                    Diagram::of(Diagram::architecture()
                        ->title('Checkout platform')
                        ->group('Users', 'Users')
                        ->person('Customer', 'Customer', 'Users')
                        ->group('Web', 'Web')
                        ->component('App', 'App', 'Web')
                        ->component('Api', 'API', 'Web')
                        ->group('Data', 'Data')
                        ->database('Orders', 'Orders DB', 'Data')
                        ->relationship('Customer', 'App', 'uses')
                        ->relationship('App', 'Api', 'calls')
                        ->relationship('Api', 'Orders', 'writes')
                        ->build()),
                    <<<'SOURCE'
                    architecture
                        title Checkout platform
                        group Users [Users]
                            person Customer [Customer]
                        group Web [Web]
                            component App [App]
                            component Api [API]
                        group Data [Data]
                            database Orders [Orders DB]
                        Customer -> App : uses
                        App -> Api : calls
                        Api -> Orders : writes
                    SOURCE,
                    ['Relationship labels should remain secondary to node labels.', 'Group labels should not collide with child nodes.'],
                ),
                new LabelPolishCase(
                    'long-cross-tier',
                    'Long cross-tier labels',
                    'Long relationship labels crossing groups should stay readable and not mask group structure.',
                    Diagram::of(Diagram::architecture()
                        ->title('Compliance evidence platform')
                        ->group('Operators', 'Internal operators and auditors')
                        ->person('Auditor', 'External compliance auditor', 'Operators')
                        ->person('Admin', 'Tenant administrator', 'Operators')
                        ->group('Application', 'Application services')
                        ->component('Console', 'Evidence export console', 'Application')
                        ->component('Api', 'Compliance API', 'Application')
                        ->queue('Jobs', 'Evidence package jobs', 'Application')
                        ->group('Storage', 'Storage and audit trail')
                        ->database('Events', 'Immutable audit events', 'Storage')
                        ->database('Files', 'Signed evidence archive', 'Storage')
                        ->relationship('Auditor', 'Console', 'reviews exported package')
                        ->relationship('Admin', 'Console', 'requests evidence for selected period')
                        ->relationship('Console', 'Api', 'submits signed export request')
                        ->relationship('Api', 'Jobs', 'queues long-running assembly work')
                        ->relationship('Jobs', 'Events', 'reads normalized audit event stream')
                        ->relationship('Jobs', 'Files', 'writes signed immutable archive')
                        ->build()),
                    <<<'SOURCE'
                    architecture
                        title Compliance evidence platform
                        group Operators [Internal operators and auditors]
                            person Auditor [External compliance auditor]
                            person Admin [Tenant administrator]
                        group Application [Application services]
                            component Console [Evidence export console]
                            component Api [Compliance API]
                            queue Jobs [Evidence package jobs]
                        group Storage [Storage and audit trail]
                            database Events [Immutable audit events]
                            database Files [Signed evidence archive]
                        Auditor -> Console : reviews exported package
                        Admin -> Console : requests evidence for selected period
                        Console -> Api : submits signed export request
                        Api -> Jobs : queues long-running assembly work
                        Jobs -> Events : reads normalized audit event stream
                        Jobs -> Files : writes signed immutable archive
                    SOURCE,
                    ['Cross-tier labels should not obscure group borders.', 'Long node labels should still show node kind hierarchy.', 'Queued/database labels and relationship labels should not merge visually.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'c4',
            'C4 Labels',
            'People, systems, containers, boundaries, technologies, and relationship labels.',
            [
                new LabelPolishCase(
                    'container',
                    'Container view labels',
                    'C4 container views should keep element kind, label, technology, and relationship text distinct.',
                    Diagram::of(Diagram::c4()
                        ->containerView()
                        ->title('Shop platform')
                        ->person('Buyer', 'Buyer', 'Places and tracks orders')
                        ->externalSystem('Stripe', 'Stripe', 'Payment provider')
                        ->boundary('Shop', 'Shop Platform')
                            ->container('Web', 'Web App', 'Symfony')
                            ->container('Api', 'API', 'PHP')
                            ->database('Db', 'Orders DB', 'PostgreSQL')
                        ->endBoundary()
                        ->relationship('Buyer', 'Web', 'uses')
                        ->relationship('Web', 'Api', 'submits checkout', 'HTTPS')
                        ->relationship('Api', 'Db', 'reads and writes')
                        ->relationship('Api', 'Stripe', 'charges card', 'HTTPS')
                        ->build()),
                    <<<'SOURCE'
                    C4Container
                        title Shop platform
                        Person(Buyer, "Buyer", "Places and tracks orders")
                        System_Ext(Stripe, "Stripe", "Payment provider")
                        System_Boundary(Shop, "Shop Platform") {
                            Container(Web, "Web App", "Symfony")
                            Container(Api, "API", "PHP")
                            ContainerDb(Db, "Orders DB", "PostgreSQL")
                        }
                        Rel(Buyer, Web, "uses")
                        Rel(Web, Api, "submits checkout", "HTTPS")
                        Rel(Api, Db, "reads and writes")
                        Rel(Api, Stripe, "charges card", "HTTPS")
                    SOURCE,
                    ['Technology labels should stay secondary inside element boxes.', 'Relationship labels should not merge with boundary headers.', 'External systems need enough contrast without looking like primary containers.'],
                ),
                new LabelPolishCase(
                    'long-relationships',
                    'Long C4 relationship labels',
                    'Long cross-boundary relationships should remain readable without hiding the system boundary.',
                    Diagram::of(Diagram::c4()
                        ->componentView()
                        ->title('Compliance export API')
                        ->externalPerson('Auditor', 'External compliance auditor', 'Reviews evidence packages')
                        ->boundary('Api', 'Compliance Export API')
                            ->component('Console', 'Evidence export console', 'PHP')
                            ->component('Assembler', 'Evidence package assembler', 'Worker')
                            ->componentDatabase('Archive', 'Signed evidence archive', 'Object storage')
                        ->endBoundary()
                        ->relationship('Auditor', 'Console', 'requests evidence for a selected audit period')
                        ->relationship('Console', 'Assembler', 'queues signed export assembly work')
                        ->relationship('Assembler', 'Archive', 'writes immutable evidence package')
                        ->build()),
                    <<<'SOURCE'
                    C4Component
                        title Compliance export API
                        Person_Ext(Auditor, "External compliance auditor", "Reviews evidence packages")
                        System_Boundary(Api, "Compliance Export API") {
                            Component(Console, "Evidence export console", "PHP")
                            Component(Assembler, "Evidence package assembler", "Worker")
                            ComponentDb(Archive, "Signed evidence archive", "Object storage")
                        }
                        Rel(Auditor, Console, "requests evidence for a selected audit period")
                        Rel(Console, Assembler, "queues signed export assembly work")
                        Rel(Assembler, Archive, "writes immutable evidence package")
                    SOURCE,
                    ['Long labels need a clear halo over both canvas and boundary fills.', 'Component labels should not wrap into relationship label zones.', 'External person text should remain readable at the diagram edge.'],
                ),
            ],
        ),
        new LabelPolishPage(
            'venn',
            'Venn Labels',
            'Set labels, region labels, cardinality values, and the set legend.',
            [
                new LabelPolishCase(
                    'legend',
                    'Two-set legend and region labels',
                    'A simple Venn checks set legend balance and small region labels.',
                    Diagram::of((new VennDiagramBuilder())
                        ->title('Skill overlap')
                        ->set('Frontend', 42)
                        ->set('Backend', 35)
                        ->regionLabel('A', 'CSS')
                        ->regionLabel('B', 'SQL')
                        ->regionLabel('AB', 'HTTP')
                        ->withLegend()
                        ->build()),
                    <<<'SOURCE'
                    venn builder data
                        title: Skill overlap
                        sets: Frontend 42, Backend 35
                        region A: CSS
                        region B: SQL
                        region AB: HTTP
                        legend: enabled
                    SOURCE,
                    ['Legend labels should align with swatches.', 'Region labels should not look like set labels.', 'Cardinality values should stay secondary.'],
                ),
                new LabelPolishCase(
                    'long-regions',
                    'Long set and region labels',
                    'Long labels stress small overlap areas and legend row width.',
                    Diagram::of((new VennDiagramBuilder())
                        ->title('Customer insight sources')
                        ->set('Product analytics events', 128)
                        ->set('Support conversations', 64)
                        ->set('Sales discovery notes', 32)
                        ->regionLabel('A', 'usage')
                        ->regionLabel('B', 'pain points')
                        ->regionLabel('C', 'intent')
                        ->regionLabel('AB', 'friction evidence')
                        ->regionLabel('AC', 'expansion signals')
                        ->regionLabel('BC', 'buyer objections')
                        ->regionLabel('ABC', 'priority account narrative')
                        ->withLegend()
                        ->build()),
                    <<<'SOURCE'
                    venn builder data
                        title: Customer insight sources
                        sets: Product analytics events 128, Support conversations 64, Sales discovery notes 32
                        AB: friction evidence
                        AC: expansion signals
                        BC: buyer objections
                        ABC: priority account narrative
                        legend: enabled
                    SOURCE,
                    ['Long legend labels should not dominate the diagram.', 'The central overlap label should fit or reveal a limitation clearly.', 'Set labels and cardinalities should remain visually grouped.'],
                ),
            ],
        ),
    ];
}

/**
 * @param list<LabelPolishPage> $pages
 */
function renderLabelPolishPage(LabelPolishPage $page, array $pages): string
{
    $caseHtml = [];
    foreach ($page->cases as $case) {
        $caseHtml[] = renderLabelPolishCase($case);
    }

    $navLinks = [];
    foreach ($pages as $candidate) {
        $current = $candidate->slug === $page->slug ? ' aria-current="page"' : '';
        $navLinks[] = sprintf(
            '<a href="%s.html"%s>%s</a>',
            escapeHtml($candidate->slug),
            $current,
            escapeHtml($candidate->title),
        );
    }

    $title = escapeHtml($page->title.' - Atelier Label Polish');

    return sprintf(
        <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>%s</title>
  %s
</head>
<body>
  <header class="topbar">
    <a class="brand" href="index.html">Atelier label polish</a>
    <nav class="page-nav" aria-label="Diagram pages">%s</nav>
  </header>
  <main>
    <section class="intro">
      <p class="eyebrow">Visual audit</p>
      <h1>%s</h1>
      <p>%s</p>
    </section>
    <section class="case-grid">%s</section>
  </main>
</body>
</html>
HTML,
        $title,
        labelPolishStyles(),
        implode('', $navLinks),
        escapeHtml($page->title),
        escapeHtml($page->description),
        implode('', $caseHtml),
    );
}

function renderLabelPolishCase(LabelPolishCase $case): string
{
    $checks = [];
    foreach ($case->checks as $check) {
        $checks[] = '<li>'.escapeHtml($check).'</li>';
    }

    return sprintf(
        <<<'HTML'
<article class="case">
  <div class="case-head">
    <p class="case-kicker">%s</p>
    <h2>%s</h2>
    <p>%s</p>
  </div>
  <div class="diagram-frame">%s</div>
  <aside class="audit">
    <h3>Inspect</h3>
    <ul>%s</ul>
  </aside>
  <pre class="source"><code>%s</code></pre>
</article>
HTML,
        escapeHtml($case->slug),
        escapeHtml($case->title),
        escapeHtml($case->intent),
        $case->diagram->toSvg(Theme::default()),
        implode('', $checks),
        escapeHtml(normalizeSource($case->source)),
    );
}

/**
 * @param list<LabelPolishPage> $pages
 */
function renderLabelPolishIndex(array $pages): string
{
    $links = [];
    foreach ($pages as $page) {
        $links[] = sprintf(
            '<a class="index-card" href="%s.html"><span>%s</span><strong>%s</strong><em>%d cases</em></a>',
            escapeHtml($page->slug),
            escapeHtml($page->description),
            escapeHtml($page->title),
            \count($page->cases),
        );
    }

    return sprintf(
        <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Atelier Label Polish Audit</title>
  %s
</head>
<body>
  <main class="index">
    <p class="eyebrow">Visual audit</p>
    <h1>Atelier Label Polish</h1>
    <p class="lead">One page per diagram family, with short and long labels rendered from real builders.</p>
    <section class="index-grid">%s</section>
  </main>
</body>
</html>
HTML,
        labelPolishStyles(),
        implode('', $links),
    );
}

function labelPolishStyles(): string
{
    return <<<'HTML'
<style>
  :root {
    color-scheme: light;
    --bg: #f8fafc;
    --surface: #ffffff;
    --surface-2: #eef2f7;
    --border: #cbd5e1;
    --text: #172033;
    --muted: #607089;
    --accent: #0f766e;
    --accent-2: #1d4ed8;
    --code: #111827;
  }

  * { box-sizing: border-box; }

  html, body {
    margin: 0;
    min-height: 100%;
    background: var(--bg);
    color: var(--text);
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }

  .topbar {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 12px 18px;
    border-bottom: 1px solid var(--border);
    background: rgba(248, 250, 252, 0.92);
    backdrop-filter: blur(12px);
  }

  .brand {
    flex: 0 0 auto;
    color: var(--text);
    font-weight: 800;
    text-decoration: none;
  }

  .page-nav {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    scrollbar-width: thin;
  }

  .page-nav a {
    flex: 0 0 auto;
    padding: 7px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--muted);
    font-size: 13px;
    line-height: 1;
    text-decoration: none;
  }

  .page-nav a[aria-current="page"],
  .page-nav a:hover,
  .page-nav a:focus-visible {
    border-color: rgba(15, 118, 110, 0.45);
    color: var(--accent);
    outline: none;
  }

  main {
    width: min(1440px, 100%);
    margin: 0 auto;
    padding: 32px 18px 48px;
  }

  .intro,
  .index {
    max-width: 860px;
  }

  .eyebrow,
  .case-kicker {
    margin: 0 0 8px;
    color: var(--accent);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: uppercase;
  }

  h1 {
    margin: 0;
    font-size: clamp(32px, 5vw, 56px);
    line-height: 1.02;
    letter-spacing: 0;
  }

  .intro p,
  .lead {
    margin: 12px 0 0;
    color: var(--muted);
    font-size: 16px;
    line-height: 1.55;
  }

  .case-grid {
    display: grid;
    gap: 28px;
    margin-top: 28px;
  }

  .case {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 0.34fr);
    grid-template-areas:
      "head audit"
      "diagram audit"
      "source source";
    gap: 14px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
  }

  .case-head { grid-area: head; }

  .case-head h2 {
    margin: 0;
    font-size: 22px;
    line-height: 1.15;
    letter-spacing: 0;
  }

  .case-head p:last-child {
    margin: 8px 0 0;
    color: var(--muted);
    line-height: 1.45;
  }

  .diagram-frame {
    grid-area: diagram;
    min-height: 420px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    border: 1px solid #dbe3ee;
    border-radius: 6px;
    background: #fdfefe;
    overflow: auto;
  }

  .diagram-frame svg {
    max-width: 100%;
    height: auto;
    display: block;
  }

  .audit {
    grid-area: audit;
    padding: 14px;
    border: 1px solid #dbe3ee;
    border-radius: 6px;
    background: var(--surface-2);
  }

  .audit h3 {
    margin: 0 0 10px;
    color: var(--accent-2);
    font-size: 14px;
  }

  .audit ul {
    margin: 0;
    padding-left: 18px;
    color: var(--text);
    font-size: 14px;
    line-height: 1.45;
  }

  .audit li + li { margin-top: 8px; }

  .source {
    grid-area: source;
    margin: 0;
    max-height: 280px;
    overflow: auto;
    padding: 14px;
    border-radius: 6px;
    background: var(--code);
    color: #e5e7eb;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
    font-size: 12px;
    line-height: 1.45;
  }

  .index-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 12px;
    margin-top: 26px;
    width: min(1120px, calc(100vw - 36px));
  }

  .index-card {
    display: grid;
    gap: 8px;
    min-height: 150px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    text-decoration: none;
  }

  .index-card:hover,
  .index-card:focus-visible {
    border-color: rgba(15, 118, 110, 0.45);
    outline: none;
  }

  .index-card strong {
    font-size: 20px;
    line-height: 1.15;
  }

  .index-card span {
    color: var(--muted);
    line-height: 1.4;
  }

  .index-card em {
    align-self: end;
    color: var(--accent);
    font-style: normal;
    font-size: 13px;
    font-weight: 700;
  }

  @media (max-width: 920px) {
    .topbar {
      align-items: stretch;
      flex-direction: column;
      gap: 10px;
    }

    .case {
      grid-template-columns: 1fr;
      grid-template-areas:
        "head"
        "diagram"
        "audit"
        "source";
    }

    .diagram-frame {
      min-height: 300px;
      justify-content: flex-start;
    }
  }
</style>
HTML;
}

function ensureDirectory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0o755, true)) {
        throw new RuntimeException(sprintf('Cannot create output directory: %s', $path));
    }
}

function writeFile(string $path, string $contents): void
{
    if (false === file_put_contents($path, $contents)) {
        throw new RuntimeException(sprintf('Cannot write file: %s', $path));
    }
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
}

function normalizeSource(string $source): string
{
    $source = trim($source);
    $lines = explode("\n", $source);
    $indent = null;
    foreach ($lines as $line) {
        if ('' === trim($line)) {
            continue;
        }
        preg_match('/^\s*/', $line, $matches);
        $width = \strlen($matches[0] ?? '');
        $indent = null === $indent ? $width : min($indent, $width);
    }

    if (null === $indent || 0 === $indent) {
        return $source;
    }

    return implode("\n", array_map(
        static fn (string $line): string => substr($line, $indent),
        $lines,
    ));
}
