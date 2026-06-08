Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$outputDir = [System.IO.Path]::GetFullPath((Join-Path $repoRoot 'docs\presentations'))
$outputPath = [System.IO.Path]::GetFullPath((Join-Path $outputDir 'CleanUx_Dossier_budget_API_deploiement_Associes_2026-06-01.docx'))

if (-not $outputPath.StartsWith($repoRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Le chemin de sortie doit rester dans le workspace."
}

New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
if (Test-Path -LiteralPath $outputPath) {
    Remove-Item -LiteralPath $outputPath -Force
}

function ConvertTo-XmlText {
    param([AllowEmptyString()][string]$Text)

    return [System.Security.SecurityElement]::Escape($Text)
}

function New-RunXml {
    param(
        [AllowEmptyString()][string]$Text,
        [bool]$Bold = $false,
        [bool]$Italic = $false,
        [string]$Color = '',
        [int]$Size = 0
    )

    $properties = ''
    if ($Bold) { $properties += '<w:b/>' }
    if ($Italic) { $properties += '<w:i/>' }
    if ($Color) { $properties += "<w:color w:val=`"$Color`"/>" }
    if ($Size -gt 0) { $properties += "<w:sz w:val=`"$Size`"/><w:szCs w:val=`"$Size`"/>" }
    if ($properties) { $properties = "<w:rPr>$properties</w:rPr>" }

    return "<w:r>$properties<w:t xml:space=`"preserve`">$(ConvertTo-XmlText $Text)</w:t></w:r>"
}

function Add-Paragraph {
    param(
        [AllowEmptyString()][string]$Text = '',
        [string]$Style = 'Normal',
        [string]$Align = '',
        [bool]$Bold = $false,
        [bool]$Italic = $false,
        [string]$Color = '',
        [int]$Size = 0,
        [int]$SpaceAfter = -1
    )

    $paragraphProperties = "<w:pStyle w:val=`"$Style`"/>"
    if ($Align) { $paragraphProperties += "<w:jc w:val=`"$Align`"/>" }
    if ($SpaceAfter -ge 0) { $paragraphProperties += "<w:spacing w:after=`"$SpaceAfter`"/>" }

    [void]$script:body.AppendLine(
        "<w:p><w:pPr>$paragraphProperties</w:pPr>$(New-RunXml -Text $Text -Bold $Bold -Italic $Italic -Color $Color -Size $Size)</w:p>"
    )
}

function Add-Bullet {
    param([string]$Text)
    Add-Paragraph -Text "• $Text" -Style 'Bullet'
}

function Add-PageBreak {
    [void]$script:body.AppendLine('<w:p><w:r><w:br w:type="page"/></w:r></w:p>')
}

function Add-Table {
    param(
        [string[]]$Headers,
        [object[]]$Rows,
        [int[]]$Widths = @()
    )

    $columnCount = $Headers.Count
    if ($Widths.Count -ne $columnCount) {
        $defaultWidth = [Math]::Floor(9000 / $columnCount)
        $Widths = @(for ($i = 0; $i -lt $columnCount; $i++) { $defaultWidth })
    }

    $table = New-Object System.Text.StringBuilder
    [void]$table.AppendLine('<w:tbl>')
    [void]$table.AppendLine(@'
<w:tblPr>
  <w:tblW w:w="9000" w:type="dxa"/>
  <w:tblLayout w:type="fixed"/>
  <w:tblBorders>
    <w:top w:val="single" w:sz="4" w:space="0" w:color="B7C9D6"/>
    <w:left w:val="single" w:sz="4" w:space="0" w:color="B7C9D6"/>
    <w:bottom w:val="single" w:sz="4" w:space="0" w:color="B7C9D6"/>
    <w:right w:val="single" w:sz="4" w:space="0" w:color="B7C9D6"/>
    <w:insideH w:val="single" w:sz="4" w:space="0" w:color="D9E4EC"/>
    <w:insideV w:val="single" w:sz="4" w:space="0" w:color="D9E4EC"/>
  </w:tblBorders>
  <w:tblCellMar>
    <w:top w:w="80" w:type="dxa"/>
    <w:left w:w="90" w:type="dxa"/>
    <w:bottom w:w="80" w:type="dxa"/>
    <w:right w:w="90" w:type="dxa"/>
  </w:tblCellMar>
</w:tblPr>
'@)
    [void]$table.AppendLine('<w:tblGrid>')
    foreach ($width in $Widths) {
        [void]$table.AppendLine("<w:gridCol w:w=`"$width`"/>")
    }
    [void]$table.AppendLine('</w:tblGrid>')

    [void]$table.AppendLine('<w:tr>')
    for ($i = 0; $i -lt $columnCount; $i++) {
        $header = ConvertTo-XmlText $Headers[$i]
        $width = $Widths[$i]
        [void]$table.AppendLine(
            "<w:tc><w:tcPr><w:tcW w:w=`"$width`" w:type=`"dxa`"/><w:shd w:fill=`"1F4E79`"/></w:tcPr><w:p><w:pPr><w:spacing w:after=`"0`"/></w:pPr><w:r><w:rPr><w:b/><w:color w:val=`"FFFFFF`"/></w:rPr><w:t>$header</w:t></w:r></w:p></w:tc>"
        )
    }
    [void]$table.AppendLine('</w:tr>')

    $rowIndex = 0
    foreach ($row in $Rows) {
        $fill = if (($rowIndex % 2) -eq 0) { 'F5F9FC' } else { 'FFFFFF' }
        [void]$table.AppendLine('<w:tr>')
        for ($i = 0; $i -lt $columnCount; $i++) {
            $value = if ($i -lt $row.Count) { [string]$row[$i] } else { '' }
            $cell = ConvertTo-XmlText $value
            $width = $Widths[$i]
            [void]$table.AppendLine(
                "<w:tc><w:tcPr><w:tcW w:w=`"$width`" w:type=`"dxa`"/><w:shd w:fill=`"$fill`"/></w:tcPr><w:p><w:pPr><w:spacing w:after=`"0`"/></w:pPr><w:r><w:t xml:space=`"preserve`">$cell</w:t></w:r></w:p></w:tc>"
            )
        }
        [void]$table.AppendLine('</w:tr>')
        $rowIndex++
    }

    [void]$table.AppendLine('</w:tbl>')
    [void]$table.AppendLine('<w:p><w:pPr><w:spacing w:after="80"/></w:pPr></w:p>')
    [void]$script:body.AppendLine($table.ToString())
}

function Add-Callout {
    param(
        [string]$Title,
        [string]$Text,
        [string]$Fill = 'EAF2F8'
    )

    $titleXml = ConvertTo-XmlText $Title
    $textXml = ConvertTo-XmlText $Text
    [void]$script:body.AppendLine(@"
<w:tbl>
  <w:tblPr>
    <w:tblW w:w="9000" w:type="dxa"/>
    <w:tblBorders>
      <w:top w:val="single" w:sz="6" w:space="0" w:color="1F4E79"/>
      <w:left w:val="single" w:sz="12" w:space="0" w:color="1F4E79"/>
      <w:bottom w:val="single" w:sz="6" w:space="0" w:color="1F4E79"/>
      <w:right w:val="single" w:sz="6" w:space="0" w:color="1F4E79"/>
    </w:tblBorders>
  </w:tblPr>
  <w:tr>
    <w:tc>
      <w:tcPr><w:shd w:fill="$Fill"/><w:tcMar><w:top w:w="120" w:type="dxa"/><w:left w:w="140" w:type="dxa"/><w:bottom w:w="120" w:type="dxa"/><w:right w:w="140" w:type="dxa"/></w:tcMar></w:tcPr>
      <w:p><w:pPr><w:spacing w:after="50"/></w:pPr><w:r><w:rPr><w:b/><w:color w:val="1F4E79"/></w:rPr><w:t>$titleXml</w:t></w:r></w:p>
      <w:p><w:pPr><w:spacing w:after="0"/></w:pPr><w:r><w:t xml:space="preserve">$textXml</w:t></w:r></w:p>
    </w:tc>
  </w:tr>
</w:tbl>
<w:p><w:pPr><w:spacing w:after="100"/></w:pPr></w:p>
"@)
}

$script:body = New-Object System.Text.StringBuilder

# Cover
Add-Paragraph -Text 'CLEANUX' -Style 'CoverBrand' -Align 'center' -Bold $true -Color '1F4E79' -Size 42 -SpaceAfter 60
Add-Paragraph -Text 'DOSSIER BUDGÉTAIRE' -Style 'CoverTitle' -Align 'center' -Bold $true -Color '17365D' -Size 48 -SpaceAfter 40
Add-Paragraph -Text 'API, infrastructure et déploiement App Store / Play Store' -Style 'CoverSubtitle' -Align 'center' -Color '365F91' -Size 28 -SpaceAfter 180
Add-Paragraph -Text 'Scénario prudent : activation étendue des services prévus dans le projet' -Style 'CoverSubtitle' -Align 'center' -Italic $true -Color '4F6475' -Size 24 -SpaceAfter 260
Add-Paragraph -Text 'Document préparé pour présentation aux associés' -Style 'CoverMeta' -Align 'center' -Bold $true -Color '1F1F1F' -Size 22 -SpaceAfter 30
Add-Paragraph -Text 'Version du 1er juin 2026' -Style 'CoverMeta' -Align 'center' -Color '5B6573' -Size 20 -SpaceAfter 260
Add-Callout -Title 'Nature du document' -Text 'Ce dossier est une estimation budgétaire de pilotage, pas une facture fournisseur. Les montants sur devis sont volontairement couverts par des provisions prudentes. Une marge de sécurité de 25 % est affichée séparément pour éviter une sous-estimation lors du lancement.'

Add-PageBreak

# Executive summary
Add-Paragraph -Text '1. Synthèse exécutive' -Style 'Heading1'
Add-Paragraph -Text 'CleanUx est une marketplace multi-métiers qui organise la relation entre clients particuliers, clients entreprises, prestataires indépendants et sociétés prestataires. Le projet prévoit deux applications mobiles React Native Expo, une interface web Laravel et un ensemble d''intégrations pour le paiement, la communication, la conformité, la géolocalisation et l''exploitation.'
Add-Paragraph -Text 'Le scénario étudié ici est volontairement conservateur : il couvre l''ouverture et l''activation de tous les services visibles dans le dépôt, y compris plusieurs alternatives qui ne seraient normalement jamais payées simultanément.'
Add-Callout -Title 'Recommandation de trésorerie' -Text 'Prévoir une enveloppe de lancement de 4 300 € et une enveloppe mensuelle prudente de 3 100 €. Pour couvrir le premier trimestre sans tension, la réserve recommandée est arrondie à 14 000 €, hors salaires, marketing, développement, fiscalité et sinistres d''assurance.' -Fill 'E2F0D9'

Add-Table -Headers @('Indicateur', 'Montant recommandé', 'Lecture') -Widths @(3000, 1900, 4100) -Rows @(
    @('Mise en route et déploiement Stores', '4 300 €', 'Frais officiels, préparation, QA, conformité et marge de sécurité'),
    @('Budget mensuel prudent', '3 100 € / mois', 'Toutes les API activées, trafic pilote, contrats sur devis provisionnés'),
    @('Réserve de trésorerie sur 3 mois', '14 000 €', 'Montant arrondi pour absorber les écarts de facturation'),
    @('Marge de sécurité intégrée', '25 %', 'Présentée séparément pour rester transparente')
)

Add-Paragraph -Text 'Décisions recommandées aux associés' -Style 'Heading2'
Add-Bullet -Text 'Valider une enveloppe maximale de 14 000 € pour le trimestre de lancement technique.'
Add-Bullet -Text 'Négocier Onfido / Entrust, le screening sanctions et les partenariats assurance avant engagement définitif.'
Add-Bullet -Text 'N''activer réellement que les fournisseurs utiles en production après comparaison des devis.'
Add-Bullet -Text 'Conserver la marge de sécurité de 25 % jusqu''à trois mois de facturation réelle.'

# Method
Add-Paragraph -Text '2. Méthode de chiffrage' -Style 'Heading1'
Add-Paragraph -Text 'Le dépôt CleanUx contient des services actifs, des fallbacks et des alternatives techniques. Afin de répondre à l''hypothèse « tout activer sans exception », le budget inclut les offres payantes minimales utiles lorsqu''un prix public existe, puis ajoute des provisions explicites lorsque les fournisseurs fonctionnent sur devis.'
Add-Table -Headers @('Hypothèse', 'Valeur retenue', 'Pourquoi') -Widths @(2800, 2000, 4200) -Rows @(
    @('Marge de sécurité', '25 %', 'Absorber variation de change, TVA éventuelle, dépassement de quota et frais oubliés'),
    @('Paiements Stripe', '10 000 € de GMV / mois', 'Simulation de démarrage avec environ 200 paiements'),
    @('SMS', '1 000 SMS Twilio / mois', 'OTP, notifications et marge opérationnelle'),
    @('Emails', '10 000 emails / mois', 'Transactions, notifications et tests des fournisseurs alternatifs'),
    @('Cartographie', 'Trafic pilote', 'Quotas gratuits possibles, mais réserve prévue pour éviter une surprise'),
    @('KYC et conformité', 'Réserves prudentes', 'Les tarifs réels dépendront des devis et du nombre de contrôles'),
    @('Devise', 'EUR arrondis', 'Les services facturés en USD sont convertis avec une marge de prudence')
)
Add-Callout -Title 'Point de gouvernance' -Text 'Les montants « provision » ne sont pas présentés comme des prix officiels. Ils constituent une réserve interne de gestion à ajuster après réception des devis.'

# Monthly budget
Add-Paragraph -Text '3. Budget mensuel prudent' -Style 'Heading1'
Add-Paragraph -Text 'Le tableau ci-dessous présente le budget mensuel maximaliste conseillé pour la phase de lancement. Il inclut des services redondants afin de couvrir l''hypothèse demandée, même si cette configuration n''est pas optimale économiquement.'
Add-Table -Headers @('Bloc', 'Contenu', 'Provision mensuelle') -Widths @(2200, 5000, 1800) -Rows @(
    @('Infrastructure', 'Serveur production, staging, stockage objet, sauvegardes et trafic', '75 €'),
    @('Communications', 'Twilio SMS, tests Vonage et marge sur consommation', '200 €'),
    @('Emails', 'Mailgun, Postmark, SendGrid et AWS SES', '55 €'),
    @('Cartographie et données', 'Google Maps, Mapbox, OpenExchangeRates, KVK et marge quotas', '127 €'),
    @('IA', 'Anthropic Claude et OpenAI en fallback', '90 €'),
    @('Monitoring et temps réel', 'Sentry Team, Pusher, Ably et Expo EAS Starter', '123 €'),
    @('KYC public', 'Veriff Essential et Sumsub Basic', '198 €'),
    @('Onfido / Entrust', 'Provision contractuelle en attente de devis', '350 €'),
    @('Screening sanctions', 'Provision fournisseur conformité en attente de devis', '300 €'),
    @('Assurance Hiscox', 'Provision partenariat et intégration en attente de devis', '300 €'),
    @('Assurance Wakam', 'Provision partenariat et intégration en attente de devis', '300 €'),
    @('Stripe et Connect', 'Simulation sur 10 000 € de GMV mensuel', '300 €'),
    @('Sous-total avant marge', '', '2 418 €'),
    @('Marge de sécurité', '25 % du sous-total', '605 €'),
    @('ENVELOPPE MENSUELLE RECOMMANDÉE', 'Arrondi de pilotage', '3 100 €')
)

Add-Paragraph -Text 'Lecture réaliste' -Style 'Heading2'
Add-Paragraph -Text 'En exploitation normale, il ne sera pas nécessaire de conserver tous les fournisseurs alternatifs. Après sélection des partenaires définitifs, la facture mensuelle devrait pouvoir être réduite. L''objectif du montant de 3 100 € est d''éviter une tension de trésorerie pendant le démarrage, pas d''imposer une dépense permanente.'

# One-off
Add-Paragraph -Text '4. Frais de mise en route et Stores' -Style 'Heading1'
Add-Table -Headers @('Poste', 'Provision initiale', 'Commentaire') -Widths @(3200, 1700, 4100) -Rows @(
    @('Apple Developer Program', '100 €', 'Compte organisation, environ 99 USD par an'),
    @('Google Play Console', '25 €', 'Frais d''inscription uniques'),
    @('Nom de domaine et DNS', '25 €', 'Domaine annuel et marge de variation selon extension'),
    @('Crédits initiaux API', '150 €', 'SMS, IA et tests techniques avant ouverture'),
    @('Préparation Stores', '450 €', 'Captures, métadonnées, localisation et réserve pour corrections de review'),
    @('QA mobile et distribution', '650 €', 'Tests sur appareils, builds et ajustements de soumission'),
    @('Revue juridique et RGPD', '1 500 €', 'Conditions, politique de confidentialité et conformité marketplace'),
    @('Onboarding partenaires', '500 €', 'Temps administratif et configuration des fournisseurs contractuels'),
    @('Sous-total avant marge', '3 400 €', ''),
    @('Marge de sécurité', '850 €', '25 %'),
    @('ENVELOPPE DE LANCEMENT RECOMMANDÉE', '4 300 €', 'Arrondi de pilotage')
)
Add-Paragraph -Text 'Les frais Apple et Google couvrent la publication des deux applications mobiles CleanUx, client et prestataire, tant qu''elles sont publiées sous les mêmes comptes développeur.'

Add-PageBreak

# API inventory
Add-Paragraph -Text '5. Inventaire complet des API principales' -Style 'Heading1'
Add-Table -Headers @('Service', 'Usage CleanUx', 'Clé', 'Facturation', 'Budget retenu') -Widths @(1600, 2700, 900, 2300, 1500) -Rows @(
    @('Stripe + Connect', 'Paiements et reversements prestataires', 'Oui', 'Pas de frais d''ouverture ; commissions par transaction', '300 € / mois'),
    @('Twilio', 'SMS OTP et notifications', 'Oui', 'Numéro mensuel + consommation SMS', '140 € / mois'),
    @('Mailgun', 'Emails transactionnels', 'Oui', 'Quota ou abonnement selon volume', '15 € / mois'),
    @('AWS S3 compatible', 'Documents, photos et sauvegardes', 'Oui', 'Stockage, requêtes et trafic', '25 € / mois'),
    @('Firebase FCM', 'Notifications Android et fallback iOS', 'Oui', 'Gratuit', '0 €'),
    @('Apple APNs', 'Notifications iOS directes', 'Oui', 'Inclus dans Apple Developer', 'Inclus'),
    @('Onfido / Entrust', 'KYC prestataires', 'Oui', 'Sur devis', '350 € / mois'),
    @('INSEE Sirene', 'Vérification sociétés françaises', 'Oui', 'Gratuit', '0 €'),
    @('VIES', 'Validation TVA européenne', 'Non', 'Gratuit', '0 €'),
    @('Google Maps Places', 'Autocomplétion des adresses', 'Oui', 'Quotas gratuits puis usage', 'Inclus réserve Maps'),
    @('Google Maps Geocoding', 'Coordonnées GPS', 'Même clé', 'Quotas gratuits puis usage', 'Inclus réserve Maps'),
    @('Google Distance Matrix', 'Distance et durée de trajet', 'Même clé', 'Quotas gratuits puis usage', 'Inclus réserve Maps'),
    @('ECB', 'Taux de change', 'Non', 'Gratuit', '0 €'),
    @('Anthropic Claude', 'Assistant et estimation photo', 'Oui', 'Facturation aux tokens', '60 € / mois'),
    @('Sentry', 'Suivi des erreurs', 'Oui', 'Offre gratuite ou Team', '26 € / mois'),
    @('Cloudflare Turnstile', 'Protection anti-bot', 'Oui', 'Gratuit', '0 €'),
    @('Nominatim OSM', 'Géocodage historique missions', 'Non', 'Gratuit sous politique d''usage', '0 €'),
    @('Frankfurter', 'Rafraîchissement complémentaire FX', 'Non', 'Gratuit', '0 €'),
    @('Google Calendar', 'Synchronisation agenda', 'OAuth', 'Gratuit dans les quotas', '0 €')
)

Add-Paragraph -Text '6. Inventaire complet des alternatives et extensions' -Style 'Heading1'
Add-Table -Headers @('Service', 'Rôle', 'Statut budgétaire', 'Budget retenu') -Widths @(1800, 3400, 2300, 1500) -Rows @(
    @('Mapbox', 'Alternative à Google Maps', 'Clé gratuite, usage au-delà des quotas', '25 € / mois'),
    @('OpenExchangeRates', 'Alternative de taux de change', 'Offre Developer', '12 € / mois'),
    @('Companies House', 'Registre sociétés Royaume-Uni', 'Gratuit', '0 €'),
    @('KVK', 'Registre sociétés Pays-Bas', 'Abonnement + requêtes', '15 € / mois'),
    @('OpenAI', 'Fallback assistant IA', 'Crédits à la consommation', '30 € / mois'),
    @('Veriff', 'Alternative KYC', 'Minimum public', '49 € / mois'),
    @('Sumsub', 'Alternative KYC', 'Minimum public', '149 € / mois'),
    @('Hiscox', 'Assurance missions', 'Contrat sur devis', '300 € / mois'),
    @('Wakam', 'Assurance missions', 'Contrat sur devis', '300 € / mois'),
    @('Screening sanctions', 'Listes sanctions et PEP', 'Fournisseur à sélectionner', '300 € / mois'),
    @('Vonage', 'Alternative SMS', 'Consommation test provisionnée', '60 € / mois'),
    @('Twilio Proxy', 'Appels masqués', 'Fermé aux nouveaux clients', 'À remplacer'),
    @('AWS SES', 'Alternative email économique', 'Usage très faible coût', '5 € / mois'),
    @('Postmark', 'Alternative email', 'Palier de base', '15 € / mois'),
    @('SendGrid', 'Alternative email', 'Palier de base', '20 € / mois'),
    @('Pusher', 'Alternative temps réel', 'Palier Startup', '49 € / mois'),
    @('Ably', 'Alternative temps réel', 'Palier Standard', '29 € / mois'),
    @('PostHog', 'Analytics produit', 'Quota gratuit suffisant au démarrage', '0 €'),
    @('Google Analytics', 'Analytics web', 'Gratuit', '0 €'),
    @('Mixpanel', 'Analytics produit alternatif', 'Quota gratuit au démarrage', '0 €'),
    @('Expo EAS Starter', 'Builds et soumissions mobiles', 'Abonnement de confort', '19 € / mois')
)

# Technical risks
Add-Paragraph -Text '7. Points techniques avant engagement financier' -Style 'Heading1'
Add-Paragraph -Text 'Payer un compte fournisseur n''améliore pas automatiquement l''application. Plusieurs intégrations existent dans la configuration mais demandent encore une décision ou un raccordement complet.'
Add-Bullet -Text 'Veriff et Sumsub sont présents dans la configuration, mais seul Onfido est raccordé au runtime KYC.'
Add-Bullet -Text 'Vonage apparaît comme alternative SMS, mais son provider n''est pas encore implémenté.'
Add-Bullet -Text 'KVK est configuré, mais il n''est pas encore résolu par le provider KYB.'
Add-Bullet -Text 'La Belgique nécessite une intégration BCE / KBO adaptée avant un onboarding B2B complet.'
Add-Bullet -Text 'Le screening sanctions réel est encore simulé et doit être contractualisé.'
Add-Bullet -Text 'Twilio Proxy doit être remplacé pour les nouveaux comptes, car l''offre n''est plus ouverte aux nouveaux clients.'
Add-Bullet -Text 'Turnstile doit être renseigné en production afin d''éviter une erreur à l''inscription.'
Add-Bullet -Text 'Le routage APNs doit être vérifié avant soumission iOS.'

Add-Paragraph -Text '8. Stratégie recommandée' -Style 'Heading1'
Add-Table -Headers @('Phase', 'Objectif', 'Budget conseillé', 'Décision') -Widths @(1300, 3500, 1600, 2600) -Rows @(
    @('Phase 1', 'Publier un pilote avec les intégrations réellement utiles', '300 à 600 € / mois', 'Stripe, Twilio, Maps, stockage, emails, push, Sentry et IA'),
    @('Phase 2', 'Signer conformité et assurance', 'Ajouter les devis réels', 'Choisir un seul KYC et les partenaires obligatoires'),
    @('Phase 3', 'Optimiser après 3 mois de factures', 'Réduire les redondances', 'Conserver uniquement les fournisseurs retenus'),
    @('Plafond prudent', 'Couvrir le scénario maximaliste pendant le lancement', '3 100 € / mois', 'Autorisation budgétaire, pas objectif de dépense')
)

# Sources
Add-Paragraph -Text '9. Sources officielles principales' -Style 'Heading1'
Add-Paragraph -Text 'Les tarifs publics évoluent. Les sources ci-dessous doivent être revérifiées avant signature ou achat de crédits.'
$sources = @(
    'Stripe Belgique : https://stripe.com/en-be/pricing',
    'Twilio SMS Belgique : https://www.twilio.com/fr-fr/sms/pricing/be',
    'Google Maps Platform : https://developers.google.com/maps/billing-and-pricing/pricing',
    'Firebase : https://firebase.google.com/pricing',
    'Cloudflare Turnstile : https://developers.cloudflare.com/turnstile/plans/',
    'Apple Developer Program : https://developer.apple.com/programs/enroll/',
    'Google Play Console : https://support.google.com/googleplay/android-developer/answer/6112435?hl=fr',
    'Expo EAS : https://expo.dev/pricing',
    'AWS S3 : https://aws.amazon.com/s3/pricing/',
    'AWS SES : https://aws.amazon.com/ses/pricing/',
    'Mailgun : https://www.mailgun.com/pricing/',
    'Postmark : https://postmarkapp.com/pricing/',
    'Sentry : https://sentry.io/pricing/',
    'Anthropic Claude : https://platform.claude.com/docs/en/about-claude/pricing',
    'OpenAI : https://platform.openai.com/docs/pricing',
    'Veriff : https://www.veriff.com/plans',
    'Sumsub : https://sumsub.com/pricing/',
    'KVK : https://www.kvk.nl/en/ordering-products/kvk-api/',
    'OpenExchangeRates : https://openexchangerates.org/signup/free',
    'Mapbox : https://www.mapbox.com/pricing',
    'PostHog : https://posthog.com/pricing',
    'Pusher : https://pusher.com/channels/pricing/',
    'Ably : https://ably.com/pricing',
    'ECB : https://data.ecb.europa.eu/help/api/overview',
    'VIES : https://ec.europa.eu/taxation_customs/vies/',
    'Nominatim : https://operations.osmfoundation.org/policies/nominatim/'
)
foreach ($source in $sources) {
    Add-Bullet -Text $source
}

Add-Paragraph -Text '10. Conclusion' -Style 'Heading1'
Add-Paragraph -Text 'CleanUx peut être déployé avec un budget de départ limité si les intégrations sont sélectionnées avec discipline. Le présent dossier retient volontairement une enveloppe plus large afin de sécuriser la décision des associés et d''éviter une sous-capitalisation technique au moment de la publication.'
Add-Callout -Title 'Montants à valider' -Text 'Autorisation recommandée : 4 300 € pour la mise en route, puis plafond opérationnel de 3 100 € par mois pendant les trois premiers mois. Réserve trimestrielle totale : 14 000 €.' -Fill 'FFF2CC'

$stylesXml = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Aptos" w:hAnsi="Aptos" w:eastAsia="Aptos" w:cs="Aptos"/>
        <w:sz w:val="20"/>
        <w:szCs w:val="20"/>
        <w:lang w:val="fr-BE"/>
      </w:rPr>
    </w:rPrDefault>
    <w:pPrDefault>
      <w:pPr><w:spacing w:after="100" w:line="276" w:lineRule="auto"/></w:pPr>
    </w:pPrDefault>
  </w:docDefaults>
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:qFormat/>
    <w:pPr><w:spacing w:after="100" w:line="276" w:lineRule="auto"/></w:pPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:basedOn w:val="Normal"/>
    <w:next w:val="Normal"/>
    <w:qFormat/>
    <w:pPr><w:keepNext/><w:spacing w:before="280" w:after="120"/></w:pPr>
    <w:rPr><w:b/><w:color w:val="1F4E79"/><w:sz w:val="30"/><w:szCs w:val="30"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
    <w:basedOn w:val="Normal"/>
    <w:next w:val="Normal"/>
    <w:qFormat/>
    <w:pPr><w:keepNext/><w:spacing w:before="180" w:after="80"/></w:pPr>
    <w:rPr><w:b/><w:color w:val="365F91"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Bullet">
    <w:name w:val="Bullet"/>
    <w:basedOn w:val="Normal"/>
    <w:pPr><w:ind w:left="260" w:hanging="180"/><w:spacing w:after="50"/></w:pPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="CoverBrand"><w:name w:val="Cover Brand"/><w:basedOn w:val="Normal"/></w:style>
  <w:style w:type="paragraph" w:styleId="CoverTitle"><w:name w:val="Cover Title"/><w:basedOn w:val="Normal"/></w:style>
  <w:style w:type="paragraph" w:styleId="CoverSubtitle"><w:name w:val="Cover Subtitle"/><w:basedOn w:val="Normal"/></w:style>
  <w:style w:type="paragraph" w:styleId="CoverMeta"><w:name w:val="Cover Meta"/><w:basedOn w:val="Normal"/></w:style>
</w:styles>
'@

$documentXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
$($script:body.ToString())
    <w:sectPr>
      <w:footerReference w:type="default" r:id="rId1"/>
      <w:pgSz w:w="11906" w:h="16838"/>
      <w:pgMar w:top="950" w:right="850" w:bottom="850" w:left="850" w:header="400" w:footer="400" w:gutter="0"/>
    </w:sectPr>
  </w:body>
</w:document>
"@

$footerXml = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p>
    <w:pPr><w:jc w:val="center"/></w:pPr>
    <w:r><w:rPr><w:color w:val="7F8C8D"/><w:sz w:val="16"/></w:rPr><w:t>CleanUx — Dossier budgétaire associés — Page </w:t></w:r>
    <w:fldSimple w:instr="PAGE"><w:r><w:rPr><w:color w:val="7F8C8D"/><w:sz w:val="16"/></w:rPr><w:t>1</w:t></w:r></w:fldSimple>
  </w:p>
</w:ftr>
'@

$contentTypesXml = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
'@

$rootRelsXml = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
'@

$documentRelsXml = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
'@

$timestamp = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
$coreXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
                   xmlns:dc="http://purl.org/dc/elements/1.1/"
                   xmlns:dcterms="http://purl.org/dc/terms/"
                   xmlns:dcmitype="http://purl.org/dc/dcmitype/"
                   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>CleanUx — Dossier budgétaire API et déploiement</dc:title>
  <dc:subject>Budget prévisionnel présenté aux associés</dc:subject>
  <dc:creator>CleanUx</dc:creator>
  <cp:keywords>CleanUx, budget, API, infrastructure, App Store, Play Store</cp:keywords>
  <dc:description>Budget prudent des API, contrats, infrastructures et frais de déploiement mobile.</dc:description>
  <dcterms:created xsi:type="dcterms:W3CDTF">$timestamp</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">$timestamp</dcterms:modified>
</cp:coreProperties>
"@

$appXml = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"
            xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>CleanUx Budget Generator</Application>
  <Company>CleanUx</Company>
  <AppVersion>1.0</AppVersion>
</Properties>
'@

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$utf8 = New-Object System.Text.UTF8Encoding($false)
$stream = [System.IO.File]::Open($outputPath, [System.IO.FileMode]::CreateNew)
$archive = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    function Add-ZipText {
        param(
            [string]$Name,
            [string]$Content
        )

        $entry = $archive.CreateEntry($Name)
        $entryStream = $entry.Open()
        $writer = New-Object System.IO.StreamWriter($entryStream, $utf8)
        try {
            $writer.Write($Content)
        } finally {
            $writer.Dispose()
        }
    }

    Add-ZipText -Name '[Content_Types].xml' -Content $contentTypesXml
    Add-ZipText -Name '_rels/.rels' -Content $rootRelsXml
    Add-ZipText -Name 'docProps/core.xml' -Content $coreXml
    Add-ZipText -Name 'docProps/app.xml' -Content $appXml
    Add-ZipText -Name 'word/document.xml' -Content $documentXml
    Add-ZipText -Name 'word/styles.xml' -Content $stylesXml
    Add-ZipText -Name 'word/footer1.xml' -Content $footerXml
    Add-ZipText -Name 'word/_rels/document.xml.rels' -Content $documentRelsXml
} finally {
    $archive.Dispose()
    $stream.Dispose()
}

Write-Output $outputPath
