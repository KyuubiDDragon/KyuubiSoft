-- 117_enhance_contract_templates.sql
-- Adds 'source_code_purchase' contract type
-- Enhances License, SaaS, Development, Maintenance templates with new conditional clauses
-- Creates Source Code Purchase templates (DE + EN)

-- 1. Add source_code_purchase to ENUM columns
ALTER TABLE contract_templates
  MODIFY COLUMN contract_type ENUM('license','development','saas','maintenance','nda','custom','source_code_purchase') NOT NULL;

ALTER TABLE contracts
  MODIFY COLUMN contract_type ENUM('license','development','saas','maintenance','nda','custom','source_code_purchase') NOT NULL;

-- 2. Updated Softwarelizenzvertrag (DE) — tpl-license-de
UPDATE contract_templates SET content_html = '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Auftragnehmer räumt dem Auftraggeber eine {{license_type_label}} Lizenz zur Nutzung der Software <strong>{{software_name}}</strong> (Version {{software_version}}) ein.</p>
<p>Die Lizenz umfasst die Nutzung durch maximal <strong>{{max_users}}</strong> Nutzer im Gebiet <strong>{{territory_label}}</strong>.</p>
<p>Lizenzmodell: <strong>{{license_model_label}}</strong></p>
<p>Installationstyp: <strong>{{installation_type_label}}</strong></p>
</div>

<h2>§ 2 Nutzungsrechte und Urheberrecht</h2>
<div class="clause">
<p>Der Auftraggeber erhält das Recht, die Software für eigene geschäftliche Zwecke zu nutzen. Eine Unterlizenzierung oder Weitergabe an Dritte ist ohne schriftliche Zustimmung des Auftragnehmers nicht gestattet. Der Weiterverkauf der Software ist nicht gestattet.</p>
<p><strong>Zweckübertragungsregel (§ 31 Abs. 5 UrhG):</strong> Die Einräumung von Nutzungsrechten erstreckt sich nur auf die in diesem Vertrag ausdrücklich genannten Nutzungsarten. Nicht ausdrücklich eingeräumte Rechte verbleiben beim Auftragnehmer.</p>
<p>{{#source_code_access}}Der Auftraggeber erhält Zugang zum Quellcode der Software. Die Nutzung des Quellcodes ist ausschließlich auf die in diesem Vertrag genannten Zwecke beschränkt. Eine Weitergabe des Quellcodes an Dritte ist nicht gestattet. Der Auftraggeber darf den Quellcode zur Herstellung der Interoperabilität mit eigener Software untersuchen und anpassen, soweit dies nach § 69e UrhG zulässig ist.{{/source_code_access}}{{^source_code_access}}Der Quellcode wird nicht übergeben. Dem Auftraggeber ist es untersagt, die Software zu dekompilieren, zurückzuentwickeln (Reverse Engineering) oder auf sonstige Weise den Quellcode zu ermitteln, es sei denn, dies ist nach § 69e UrhG zur Herstellung der Interoperabilität zwingend erlaubt.{{/source_code_access}}</p>
<p>{{#modification_rights}}Der Auftraggeber ist berechtigt, die Software für eigene Zwecke anzupassen. Die Rechte an den Anpassungen verbleiben beim Auftraggeber, soweit sie eigenständige Schöpfungen darstellen. Die Integration der Software in eigene Produkte des Auftraggebers ist gestattet, eine Weitergabe der integrierten Software an Dritte bedarf der Zustimmung des Auftragnehmers.{{/modification_rights}}{{^modification_rights}}Änderungen an der Software sind nicht gestattet.{{/modification_rights}}</p>
<p>{{#backup_copies}}Die Erstellung von Sicherungskopien gemäß § 69d Abs. 2 UrhG ist gestattet.{{/backup_copies}}{{^backup_copies}}Die Erstellung von Sicherungskopien ist nur im gesetzlich zwingend vorgeschriebenen Umfang gemäß § 69d Abs. 2 UrhG gestattet.{{/backup_copies}}</p>
{{#affiliate_use}}<p><strong>Nutzung durch verbundene Unternehmen:</strong> Der Auftraggeber ist berechtigt, die Software auch durch verbundene Unternehmen im Sinne von § 15 AktG nutzen zu lassen, sofern die maximale Nutzeranzahl insgesamt nicht überschritten wird.</p>{{/affiliate_use}}
{{#api_access}}<p><strong>API-Zugang:</strong> Der Auftragnehmer stellt dem Auftraggeber einen API-Zugang zur Integration der Software in eigene Systeme zur Verfügung. Die API-Dokumentation wird bereitgestellt. Die Nutzung der API ist ausschließlich für eigene Geschäftszwecke des Auftraggebers gestattet.</p>{{/api_access}}
</div>

<h2>§ 3 Updates und Support</h2>
<div class="clause">
<p>{{#updates_included}}Updates sind für einen Zeitraum von {{updates_duration_months}} Monaten ab Vertragsschluss im Lizenzpreis enthalten. Dies umfasst Fehlerbehebungen und funktionale Verbesserungen. Nach Ablauf dieses Zeitraums können Updates über einen separaten Wartungsvertrag bezogen werden.{{/updates_included}}{{^updates_included}}Updates sind nicht im Lizenzpreis enthalten und können separat erworben werden.{{/updates_included}}</p>
<p>Der Auftraggeber ist nicht verpflichtet, Updates zu installieren. Die Gewährleistung bezieht sich auf die jeweils installierte Version; für Mängel, die durch unterlassene Updates verursacht werden, übernimmt der Auftragnehmer keine Haftung.</p>
<p>Support-Level: <strong>{{support_level_label}}</strong></p>
{{#is_b2c}}<p><strong>Hinweis für Verbraucher (§ 327f BGB):</strong> Der Anbieter stellt Aktualisierungen bereit, die für den Erhalt der Vertragsmäßigkeit des digitalen Produkts erforderlich sind, einschließlich Sicherheitsaktualisierungen. Diese Pflicht besteht für den Zeitraum, den der Verbraucher aufgrund der Art und des Zwecks des digitalen Produkts erwarten kann. Der Anbieter informiert den Verbraucher über verfügbare Aktualisierungen und die Folgen einer unterlassenen Installation. Gesetzlich zwingende Updatepflichten nach § 327f BGB bleiben unberührt.</p>{{/is_b2c}}
</div>

<h2>§ 4 Vergütung</h2>
<div class="clause">
<p>Die Lizenzgebühr beträgt <strong>{{total_value}} {{currency}}</strong> und ist {{payment_schedule_label}} zu entrichten.</p>
{{^is_b2c}}<p><strong>Zahlungsverzug:</strong> Im Falle des Zahlungsverzugs fallen Verzugszinsen in Höhe von 9 Prozentpunkten über dem Basiszinssatz an (§ 288 Abs. 2 BGB). Zusätzlich wird eine Mahnpauschale von 40 EUR (§ 288 Abs. 5 BGB) erhoben. Bei Zahlungsverzug von mehr als 30 Tagen ist der Auftragnehmer berechtigt, die Lizenz bis zur vollständigen Zahlung auszusetzen.</p>{{/is_b2c}}
{{#is_b2c}}<p><strong>Zahlungsverzug:</strong> Im Falle des Zahlungsverzugs fallen Verzugszinsen in Höhe von 5 Prozentpunkten über dem Basiszinssatz an (§ 288 Abs. 1 BGB).</p>{{/is_b2c}}
</div>

<h2>§ 5 Gewährleistung</h2>
<div class="clause">
<p>Der Auftragnehmer gewährleistet, dass die Software im Wesentlichen der Dokumentation entspricht. Ein Mangel liegt vor, wenn die Software nicht die vereinbarte Beschaffenheit aufweist oder sich nicht für die vertraglich vorausgesetzte Verwendung eignet.</p>
<p>{{#is_b2c}}Die Gewährleistungsfrist beträgt 24 Monate ab Bereitstellung (§ 327j BGB). Für Mängel, die sich innerhalb von 12 Monaten nach Bereitstellung zeigen, wird vermutet, dass sie bereits bei Bereitstellung vorlagen (§ 327k BGB).{{/is_b2c}}{{^is_b2c}}Die Gewährleistungsfrist beträgt {{warranty_duration_months}} Monate ab Lieferung.{{/is_b2c}}</p>
<p>Die Gewährleistung umfasst die Nachbesserung oder Ersatzlieferung nach Wahl des Auftragnehmers. Schlägt die Nachbesserung nach zwei Versuchen fehl, kann der Auftraggeber Minderung oder Rücktritt verlangen.</p>
</div>

<h2>§ 6 Haftung</h2>
<div class="clause">
<p>Der Auftragnehmer haftet unbeschränkt für Vorsatz und grobe Fahrlässigkeit sowie für Schäden an Leben, Körper oder Gesundheit, für Ansprüche aus dem Produkthaftungsgesetz sowie für arglistig verschwiegene Mängel.</p>
<p>Bei leichter Fahrlässigkeit haftet der Auftragnehmer nur bei Verletzung wesentlicher Vertragspflichten (Kardinalpflichten). Wesentliche Vertragspflichten sind solche, deren Erfüllung die ordnungsgemäße Durchführung des Vertrages überhaupt erst ermöglicht und auf deren Einhaltung der Vertragspartner regelmäßig vertrauen darf. In diesem Fall ist die Haftung auf den vorhersehbaren, vertragstypischen Schaden begrenzt.</p>
<p>Die Haftung ist in jedem Fall auf die Höhe der Lizenzgebühr, mindestens jedoch 5.000 EUR, beschränkt. Dies gilt nicht für die in Absatz 1 genannten Fälle.</p>
{{^is_b2c}}<p><strong>IP-Freistellung:</strong> Der Auftragnehmer stellt den Auftraggeber von sämtlichen Ansprüchen Dritter frei, die sich aus einer Verletzung von Schutzrechten durch die vertragsgemäße Nutzung der Software ergeben. Dies gilt nicht, soweit die Rechtsverletzung auf einer Modifikation der Software durch den Auftraggeber oder deren Nutzung in einer nicht vorgesehenen Weise beruht.</p>{{/is_b2c}}
</div>

<h2>§ 7 Laufzeit und Kündigung</h2>
<div class="clause">
<p>Der Vertrag beginnt am {{start_date}} {{#end_date}}und endet am {{end_date}}{{/end_date}}{{^end_date}}und läuft auf unbestimmte Zeit{{/end_date}}.</p>
<p>Die Kündigungsfrist beträgt {{notice_period_days}} Tage {{#end_date}}zum Ende der jeweiligen Vertragslaufzeit{{/end_date}}{{^end_date}}zum Monatsende{{/end_date}}.</p>
<p>Das Recht zur außerordentlichen Kündigung aus wichtigem Grund (§ 314 BGB) bleibt unberührt.</p>
<p>Nach Vertragsende ist der Auftraggeber verpflichtet, die Nutzung der Software einzustellen und alle Installationen zu deinstallieren. Ein Datenexport ist innerhalb von 30 Tagen nach Vertragsende möglich.</p>
</div>

{{#audit_rights}}<h2>§ 8 Audit-Recht</h2>
<div class="clause">
<p>Der Auftragnehmer ist berechtigt, einmal jährlich die vertragsgemäße Nutzung der Software zu überprüfen. Die Überprüfung erfolgt nach mindestens 30 Tagen schriftlicher Vorankündigung und während der üblichen Geschäftszeiten.</p>
<p>Die Kosten des Audits trägt der Auftragnehmer. Wird ein Verstoß gegen die Nutzungsbedingungen festgestellt, trägt der Auftraggeber die Kosten des Audits und ist zur unverzüglichen Nachzahlung der entsprechenden Lizenzgebühren verpflichtet.</p>
<p>Der Auftraggeber ist verpflichtet, auf Anfrage Auskunft über die Anzahl der Installationen und aktiven Nutzer zu erteilen.</p>
</div>{{/audit_rights}}

{{#open_source_included}}<h2>Open-Source-Komponenten</h2>
<div class="clause">
<p>Die Software enthält Open-Source-Komponenten. Der Auftragnehmer stellt eine vollständige Liste der verwendeten Open-Source-Bibliotheken mit den jeweiligen Lizenzbedingungen (z.B. MIT, Apache 2.0, BSD, GPL, LGPL) als Anlage bereit.</p>
<p>Der Auftragnehmer gewährleistet, dass die Verwendung der Open-Source-Komponenten nicht zu weitergehenden Lizenzpflichten für den proprietären Code der Software führt (insbesondere kein Copyleft-Effekt auf den proprietären Code).</p>
<p>Soweit Open-Source-Lizenzen dem Auftraggeber weitergehende Rechte einräumen als dieser Vertrag, gehen die Open-Source-Lizenzen für die jeweiligen Komponenten vor.</p>
</div>{{/open_source_included}}

{{#data_processing}}<h2>Datenschutz (DSGVO)</h2>
<div class="clause">
<p>Die Software verarbeitet personenbezogene Daten. Der Auftraggeber ist Verantwortlicher im Sinne von Art. 4 Nr. 7 DSGVO.</p>
<p>Soweit der Auftragnehmer im Rahmen von Support oder Wartung Zugang zu personenbezogenen Daten des Auftraggebers erhält, wird ein gesonderter Auftragsverarbeitungsvertrag gemäß Art. 28 DSGVO abgeschlossen.</p>
<p>Der Auftragnehmer gewährleistet, dass die Software geeignete technische und organisatorische Maßnahmen gemäß Art. 32 DSGVO unterstützt, insbesondere Verschlüsselung, Zugangskontrollen und Protokollierung.</p>
</div>{{/data_processing}}

{{#include_nda_clause}}<h2>Vertraulichkeit</h2>
<div class="clause">
<p>Die Parteien verpflichten sich, alle im Rahmen dieses Vertrages erlangten vertraulichen Informationen der jeweils anderen Partei geheim zu halten und nur für die Zwecke dieses Vertrages zu verwenden. Diese Pflicht gilt auch nach Vertragsende für einen Zeitraum von 3 Jahren fort.</p>
<p>Die Geheimhaltungspflicht gilt nicht für Informationen, die öffentlich bekannt sind, die von Dritten rechtmäßig erlangt wurden oder die aufgrund gesetzlicher Verpflichtung offengelegt werden müssen.</p>
</div>{{/include_nda_clause}}

{{#is_b2c}}<h2>Widerrufsbelehrung (Verbraucher)</h2>
<div class="clause">
<p><strong>Widerrufsrecht</strong></p>
<p>Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag des Vertragsschlusses. Um Ihr Widerrufsrecht auszuüben, müssen Sie uns ({{party_a_company}}, {{party_a_address}}, E-Mail: {{party_a_email}}) mittels einer eindeutigen Erklärung (z.B. ein mit der Post versandter Brief oder E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.</p>
<p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p>
<p><strong>Folgen des Widerrufs</strong></p>
<p>Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist. Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben.</p>
<p><strong>Besonderer Hinweis bei digitalen Inhalten (§ 356 Abs. 5 BGB):</strong> Sie stimmen ausdrücklich zu, dass wir mit der Ausführung des Vertrages vor Ablauf der Widerrufsfrist beginnen. Sie haben Kenntnis davon, dass Sie mit Beginn der Ausführung des Vertrages Ihr Widerrufsrecht verlieren.</p>
</div>{{/is_b2c}}

<h2>Höhere Gewalt</h2>
<div class="clause">
<p>Keine Partei haftet für die Nichterfüllung oder verzögerte Erfüllung ihrer Pflichten, soweit dies auf Umstände zurückzuführen ist, die außerhalb ihrer zumutbaren Kontrolle liegen (höhere Gewalt). Dazu zählen insbesondere Naturkatastrophen, Krieg, Pandemien, Streiks sowie behördliche Anordnungen.</p>
<p>Die betroffene Partei hat die andere Partei unverzüglich über den Eintritt und die voraussichtliche Dauer der höheren Gewalt zu informieren.</p>
</div>

<h2>Schlussbestimmungen</h2>
<div class="clause">
{{^is_b2c}}<p><strong>B2B-Klausel:</strong> Dieser Vertrag richtet sich ausschließlich an Unternehmer im Sinne von § 14 BGB. Der Auftraggeber bestätigt, dass er diesen Vertrag im Rahmen seiner gewerblichen oder selbständigen beruflichen Tätigkeit abschließt.</p>{{/is_b2c}}
<p>Es gilt das Recht der {{governing_law_label}}. Das Übereinkommen der Vereinten Nationen über Verträge über den internationalen Warenkauf (CISG) findet keine Anwendung.</p>
<p>{{^is_b2c}}Gerichtsstand ist {{jurisdiction}}.{{/is_b2c}}{{#is_b2c}}Für Verbraucher gilt der gesetzliche Gerichtsstand.{{/is_b2c}}</p>
<p>Änderungen und Ergänzungen dieses Vertrages bedürfen der Schriftform. Dies gilt auch für die Änderung dieser Schriftformklausel.</p>
<p>Sollte eine Bestimmung dieses Vertrages unwirksam oder undurchführbar sein, so wird die Wirksamkeit der übrigen Bestimmungen hiervon nicht berührt. Die Parteien verpflichten sich, die unwirksame Bestimmung durch eine wirksame zu ersetzen, die dem wirtschaftlichen Zweck der unwirksamen Bestimmung möglichst nahekommt.</p>
</div>
' WHERE id = 'tpl-license-de';

-- 3. Updated Software License Agreement (EN) — tpl-license-en
UPDATE contract_templates SET content_html = '
<h2>1. Subject Matter</h2>
<div class="clause">
<p>The Licensor grants the Licensee a {{license_type_label}} license to use the software <strong>{{software_name}}</strong> (Version {{software_version}}).</p>
<p>The license covers use by up to <strong>{{max_users}}</strong> users in the territory of <strong>{{territory_label}}</strong>.</p>
<p>License Model: <strong>{{license_model_label}}</strong></p>
<p>Installation Type: <strong>{{installation_type_label}}</strong></p>
</div>

<h2>2. Usage Rights and Copyright</h2>
<div class="clause">
<p>The Licensee is entitled to use the software for its own business purposes. Sublicensing or transfer to third parties requires prior written consent from the Licensor. Resale of the software is not permitted.</p>
<p><strong>Purpose Transfer Rule (§ 31(5) German Copyright Act):</strong> The grant of usage rights extends only to the types of use expressly specified in this agreement. All rights not expressly granted remain with the Licensor.</p>
<p>{{#source_code_access}}The Licensee receives access to the source code. Use of the source code is limited exclusively to the purposes stated in this agreement. Transfer of the source code to third parties is not permitted. The Licensee may examine and adapt the source code to achieve interoperability with its own software, to the extent permitted by § 69e German Copyright Act.{{/source_code_access}}{{^source_code_access}}Source code is not provided. The Licensee shall not decompile, reverse-engineer, or otherwise attempt to derive the source code of the software, except as mandated by § 69e German Copyright Act for the purpose of achieving interoperability.{{/source_code_access}}</p>
<p>{{#modification_rights}}The Licensee may modify the software for its own purposes. Rights to modifications remain with the Licensee to the extent they constitute independent works. Integration of the software into the Licensee''s own products is permitted; transfer of the integrated software to third parties requires the Licensor''s consent.{{/modification_rights}}{{^modification_rights}}Modifications to the software are not permitted.{{/modification_rights}}</p>
<p>{{#backup_copies}}The creation of backup copies pursuant to § 69d(2) German Copyright Act is permitted.{{/backup_copies}}{{^backup_copies}}The creation of backup copies is permitted only to the extent mandated by § 69d(2) German Copyright Act.{{/backup_copies}}</p>
{{#affiliate_use}}<p><strong>Affiliate Use:</strong> The Licensee is entitled to allow affiliated companies within the meaning of § 15 German Stock Corporation Act (AktG) to use the software, provided the maximum total number of users is not exceeded.</p>{{/affiliate_use}}
{{#api_access}}<p><strong>API Access:</strong> The Licensor provides the Licensee with API access for integration of the software into the Licensee''s own systems. API documentation will be provided. Use of the API is permitted exclusively for the Licensee''s own business purposes.</p>{{/api_access}}
</div>

<h2>3. Updates and Support</h2>
<div class="clause">
<p>{{#updates_included}}Updates are included for a period of {{updates_duration_months}} months from contract execution. This includes bug fixes and functional improvements. After expiry of this period, updates may be obtained via a separate maintenance agreement.{{/updates_included}}{{^updates_included}}Updates are not included in the license fee and may be purchased separately.{{/updates_included}}</p>
<p>The Licensee is not obligated to install updates. The warranty applies to the installed version; the Licensor assumes no liability for defects caused by failure to install updates.</p>
<p>Support Level: <strong>{{support_level_label}}</strong></p>
{{#is_b2c}}<p><strong>Consumer Notice (§ 327f German Civil Code):</strong> The Provider shall make available updates necessary to maintain the conformity of the digital product, including security updates. This obligation exists for the period the consumer can reasonably expect given the nature and purpose of the digital product. The Provider shall inform the consumer of available updates and the consequences of not installing them. Mandatory update obligations under § 327f BGB remain unaffected.</p>{{/is_b2c}}
</div>

<h2>4. Fees</h2>
<div class="clause">
<p>The license fee amounts to <strong>{{total_value}} {{currency}}</strong>, payable {{payment_schedule_label}}.</p>
{{^is_b2c}}<p><strong>Late Payment:</strong> In case of late payment, default interest of 9 percentage points above the base interest rate applies (§ 288(2) German Civil Code). Additionally, a flat-rate dunning fee of EUR 40 (§ 288(5) German Civil Code) will be charged. If payment is overdue by more than 30 days, the Licensor is entitled to suspend the license until full payment is received.</p>{{/is_b2c}}
{{#is_b2c}}<p><strong>Late Payment:</strong> In case of late payment, default interest of 5 percentage points above the base interest rate applies (§ 288(1) German Civil Code).</p>{{/is_b2c}}
</div>

<h2>5. Warranty</h2>
<div class="clause">
<p>The Licensor warrants that the software substantially conforms to its documentation. A defect exists if the software does not have the agreed quality or is not suitable for the contractually intended use.</p>
<p>{{#is_b2c}}The warranty period is 24 months from provision (§ 327j German Civil Code). For defects appearing within 12 months of provision, it is presumed that they existed at the time of provision (§ 327k German Civil Code).{{/is_b2c}}{{^is_b2c}}The warranty period is {{warranty_duration_months}} months from delivery.{{/is_b2c}}</p>
<p>The warranty covers repair or replacement at the Licensor''s discretion. If repair fails after two attempts, the Licensee may demand a price reduction or rescission.</p>
</div>

<h2>6. Limitation of Liability</h2>
<div class="clause">
<p>The Licensor is liable without limitation for willful misconduct and gross negligence, for damages to life, body, or health, for claims under the German Product Liability Act, and for fraudulently concealed defects.</p>
<p>In cases of slight negligence, the Licensor is liable only for breach of essential contractual obligations (cardinal obligations). Essential contractual obligations are those whose fulfilment is necessary for the proper performance of the contract and on whose compliance the contractual partner may regularly rely. In such cases, liability is limited to the foreseeable, contract-typical damage.</p>
<p>In any case, liability is limited to the amount of the license fee, but no less than EUR 5,000. This does not apply to the cases mentioned in paragraph 1.</p>
{{^is_b2c}}<p><strong>IP Indemnification:</strong> The Licensor shall indemnify and hold harmless the Licensee from all third-party claims arising from infringement of intellectual property rights caused by the contractual use of the software. This does not apply to the extent that the infringement results from modification of the software by the Licensee or its use in a manner not contemplated by this agreement.</p>{{/is_b2c}}
</div>

<h2>7. Term and Termination</h2>
<div class="clause">
<p>This agreement commences on {{start_date}} {{#end_date}}and terminates on {{end_date}}{{/end_date}}{{^end_date}}and continues for an indefinite period{{/end_date}}.</p>
<p>The notice period is {{notice_period_days}} days {{#end_date}}before the end of the respective contract period{{/end_date}}{{^end_date}}to the end of the month{{/end_date}}.</p>
<p>The right to extraordinary termination for cause (§ 314 German Civil Code) remains unaffected.</p>
<p>Upon termination, the Licensee is obligated to cease use of the software and uninstall all installations. Data export is available for 30 days after contract termination.</p>
</div>

{{#audit_rights}}<h2>8. Audit Rights</h2>
<div class="clause">
<p>The Licensor is entitled to audit the contractual use of the software once per year. The audit shall take place with at least 30 days'' prior written notice and during normal business hours.</p>
<p>The costs of the audit shall be borne by the Licensor. If a violation of the usage terms is discovered, the Licensee shall bear the audit costs and is obligated to promptly pay the corresponding license fees.</p>
<p>The Licensee is obligated to provide information on the number of installations and active users upon request.</p>
</div>{{/audit_rights}}

{{#open_source_included}}<h2>Open Source Components</h2>
<div class="clause">
<p>The software contains open-source components. The Licensor shall provide a complete list of open-source libraries used, including their respective license terms (e.g., MIT, Apache 2.0, BSD, GPL, LGPL), as an appendix.</p>
<p>The Licensor warrants that the use of open-source components does not create additional license obligations for the proprietary code of the software (in particular, no copyleft effect on the proprietary code).</p>
<p>To the extent that open-source licenses grant the Licensee broader rights than this agreement, the open-source licenses shall prevail for the respective components.</p>
</div>{{/open_source_included}}

{{#data_processing}}<h2>Data Protection (GDPR)</h2>
<div class="clause">
<p>The software processes personal data. The Licensee is the data controller within the meaning of Art. 4(7) GDPR.</p>
<p>Where the Licensor gains access to personal data of the Licensee in the course of support or maintenance, a separate Data Processing Agreement pursuant to Art. 28 GDPR shall be concluded.</p>
<p>The Licensor warrants that the software supports appropriate technical and organizational measures pursuant to Art. 32 GDPR, in particular encryption, access controls, and logging.</p>
</div>{{/data_processing}}

{{#include_nda_clause}}<h2>Confidentiality</h2>
<div class="clause">
<p>The parties undertake to keep all confidential information obtained under this agreement secret and to use it only for the purposes of this agreement. This obligation survives for a period of 3 years after termination of this agreement.</p>
<p>The confidentiality obligation does not apply to information that is publicly known, that was lawfully obtained from third parties, or that must be disclosed due to legal obligations.</p>
</div>{{/include_nda_clause}}

{{#is_b2c}}<h2>Right of Withdrawal (Consumers)</h2>
<div class="clause">
<p><strong>Right of Withdrawal</strong></p>
<p>You have the right to withdraw from this contract within fourteen days without giving any reason. The withdrawal period is fourteen days from the date of conclusion of the contract. To exercise your right of withdrawal, you must inform us ({{party_a_company}}, {{party_a_address}}, email: {{party_a_email}}) of your decision to withdraw from this contract by means of a clear declaration (e.g. a letter sent by post or email).</p>
<p>To comply with the withdrawal period, it is sufficient to send the notification of the exercise of the right of withdrawal before the withdrawal period expires.</p>
<p><strong>Consequences of Withdrawal</strong></p>
<p>If you withdraw from this contract, we shall reimburse all payments received from you without undue delay and at the latest within fourteen days from the day on which we receive the notification of your withdrawal. We shall use the same means of payment for the reimbursement as you used for the original transaction.</p>
<p><strong>Special Notice for Digital Content (§ 356(5) German Civil Code):</strong> You expressly agree that we may begin performance of the contract before the withdrawal period expires. You acknowledge that you will lose your right of withdrawal upon commencement of contract performance.</p>
</div>{{/is_b2c}}

<h2>Force Majeure</h2>
<div class="clause">
<p>Neither party shall be liable for non-performance or delayed performance of its obligations to the extent caused by circumstances beyond its reasonable control (force majeure), including natural disasters, war, pandemics, strikes, and governmental orders.</p>
<p>The affected party shall promptly notify the other party of the occurrence and expected duration of the force majeure event.</p>
</div>

<h2>General Provisions</h2>
<div class="clause">
{{^is_b2c}}<p><strong>B2B Clause:</strong> This agreement is directed exclusively at entrepreneurs within the meaning of § 14 German Civil Code (BGB). The Licensee confirms that it is entering into this agreement in the course of its commercial or independent professional activity.</p>{{/is_b2c}}
<p>This agreement is governed by the laws of {{governing_law_label}}. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.</p>
<p>{{^is_b2c}}The place of jurisdiction is {{jurisdiction}}.{{/is_b2c}}{{#is_b2c}}For consumers, the statutory place of jurisdiction applies.{{/is_b2c}}</p>
<p>Amendments to this agreement must be made in writing. This also applies to any waiver of this written form requirement.</p>
<p>If any provision of this agreement is or becomes invalid or unenforceable, the validity of the remaining provisions shall not be affected. The parties undertake to replace the invalid provision with a valid provision that most closely achieves the economic purpose of the invalid provision.</p>
</div>
' WHERE id = 'tpl-license-en';

-- 4. Updated Softwareentwicklungsvertrag (DE) — tpl-dev-de
UPDATE contract_templates SET content_html = '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Auftragnehmer verpflichtet sich zur Entwicklung folgender Software gemäß den in diesem Vertrag und dem Pflichtenheft festgelegten Spezifikationen:</p>
<p><strong>{{project_description}}</strong></p>
</div>

<h2>§ 2 Leistungsumfang und Meilensteine</h2>
<div class="clause">
<p>Die Entwicklung erfolgt in den vereinbarten Meilensteinen. Der Auftragnehmer schuldet ein funktionsfähiges Werk gemäß den vereinbarten Spezifikationen (Werkvertrag gemäß §§ 631 ff. BGB).</p>
{{#documentation_required}}<p><strong>Technische Dokumentation:</strong> Der Auftragnehmer liefert eine vollständige technische Dokumentation, bestehend aus API-Dokumentation, Installationsanleitung und Architektur-Übersicht. Die Dokumentation ist Bestandteil des geschuldeten Werks.</p>{{/documentation_required}}
{{#deployment_support}}<p><strong>Deployment-Unterstützung:</strong> Der Auftragnehmer unterstützt den Auftraggeber beim Go-Live der Software für einen Zeitraum von 5 Werktagen nach Abnahme. Dies umfasst die Unterstützung bei der Produktivsetzung, Konfiguration und Fehlerbehebung im Produktivbetrieb.</p>{{/deployment_support}}
</div>

<h2>§ 3 Mitwirkungspflichten des Auftraggebers</h2>
<div class="clause">
<p>Der Auftraggeber ist verpflichtet, den Auftragnehmer bei der Vertragsdurchführung in angemessenem Umfang zu unterstützen. Zu den Mitwirkungspflichten gehören insbesondere:</p>
<ul>
<li>Rechtzeitige Bereitstellung aller für die Entwicklung erforderlichen Informationen, Daten und Unterlagen</li>
<li>Benennung eines fachlich qualifizierten Ansprechpartners mit Entscheidungsbefugnis</li>
<li>Bereitstellung erforderlicher Testumgebungen und Zugänge</li>
<li>Rechtzeitige Rückmeldung zu vorgelegten Zwischenergebnissen (innerhalb von 5 Werktagen)</li>
<li>Mitwirkung bei der Abnahme gemäß § 5 dieses Vertrages</li>
</ul>
<p>Kommt der Auftraggeber seinen Mitwirkungspflichten nicht nach und entstehen hierdurch Verzögerungen, verlängern sich die vereinbarten Fristen entsprechend. Mehrkosten, die durch mangelnde Mitwirkung entstehen, trägt der Auftraggeber.</p>
</div>

<h2>§ 4 Änderungsverfahren (Change Requests)</h2>
<div class="clause">
<p>Änderungswünsche, die über den vereinbarten Leistungsumfang hinausgehen, sind vom Auftraggeber schriftlich als Change Request einzureichen. Der Auftragnehmer wird innerhalb von 5 Werktagen eine Aufwandsschätzung mit Kosten- und Zeitangabe vorlegen.</p>
<p>Change Requests werden erst nach schriftlicher Freigabe durch den Auftraggeber umgesetzt. Bis zur Freigabe wird die Entwicklung gemäß dem ursprünglichen Leistungsumfang fortgesetzt.</p>
</div>

<h2>§ 5 Abnahme</h2>
<div class="clause">
<p>{{acceptance_procedure}}</p>
<p>Der Auftraggeber hat die Software innerhalb von 14 Tagen nach Übergabe zu prüfen und abzunehmen oder Mängel schriftlich zu rügen. Die Abnahme gilt als erfolgt, wenn der Auftraggeber die Software ohne wesentliche Beanstandungen produktiv einsetzt oder sich nicht innerhalb der Prüffrist schriftlich äußert.</p>
<p>Unwesentliche Mängel berechtigen nicht zur Abnahmeverweigerung.</p>
</div>

<h2>§ 6 Vergütung</h2>
<div class="clause">
<p>Die Vergütung beträgt <strong>{{total_value}} {{currency}}</strong> und wird {{payment_schedule_label}} abgerechnet.</p>
</div>

<h2>§ 7 Urheberrecht und Nutzungsrechte</h2>
<div class="clause">
<p>Das Urheberrecht an der entwickelten Software verbleibt beim Auftragnehmer.</p>
<p><strong>Zweckübertragungsregel (§ 31 Abs. 5 UrhG):</strong> Der Auftraggeber erhält ein einfaches, nicht übertragbares Nutzungsrecht für eigene geschäftliche Zwecke. Nicht ausdrücklich eingeräumte Nutzungsrechte verbleiben beim Auftragnehmer.</p>
<p>Der Quellcode wird nach vollständiger Bezahlung an den Auftraggeber übergeben. Die Übergabe des Quellcodes umfasst das Recht zur Einsichtnahme, Fehlerkorrektur und Anpassung für eigene Zwecke, nicht jedoch das Recht zur Weitergabe an Dritte.</p>
<p><strong>Vorbestehende Rechte:</strong> Vorbestehende Softwarekomponenten des Auftragnehmers (Frameworks, Bibliotheken, wiederverwendbare Module) verbleiben im Eigentum des Auftragnehmers. Der Auftraggeber erhält hieran ein nicht-exklusives, zeitlich unbegrenztes Nutzungsrecht im Rahmen der entwickelten Software.</p>
</div>

<h2>§ 8 Open-Source-Compliance</h2>
<div class="clause">
<p>Der Auftragnehmer wird den Auftraggeber über die Verwendung von Open-Source-Komponenten in der Software informieren. Eine Liste der verwendeten Open-Source-Bibliotheken mit den jeweiligen Lizenzbedingungen (z.B. MIT, Apache 2.0, GPL, LGPL) wird als Anlage beigefügt.</p>
<p>Der Auftragnehmer gewährleistet, dass die Verwendung von Open-Source-Komponenten nicht zu Lizenzpflichten für die übrige Software führt (insbesondere kein Copyleft-Effekt), es sei denn, dies wurde ausdrücklich vereinbart.</p>
</div>

<h2>§ 9 Gewährleistung</h2>
<div class="clause">
<p>Die Gewährleistungsfrist beträgt {{warranty_months}} Monate ab Abnahme. Ein Mangel liegt vor, wenn die Software nicht die vereinbarte Beschaffenheit aufweist oder sich nicht für die vertraglich vorausgesetzte Verwendung eignet.</p>
<p>Der Auftragnehmer ist zur Nachbesserung verpflichtet. Bei Fehlschlagen der Nachbesserung (nach zwei Versuchen) kann der Auftraggeber Minderung oder Rücktritt verlangen.</p>
</div>

<h2>§ 10 Vertraulichkeit</h2>
<div class="clause">
<p>Beide Parteien verpflichten sich, vertrauliche Informationen der jeweils anderen Partei streng geheim zu halten und nur für die Zwecke dieses Vertrages zu verwenden. Vertrauliche Informationen umfassen insbesondere Geschäftsgeheimnisse im Sinne des GeschGehG, technische Daten, Quellcode, Kundendaten und Know-how.</p>
<p>Die empfangende Partei wird angemessene Schutzmaßnahmen treffen und den Zugang auf Mitarbeiter beschränken, die die Informationen zur Vertragserfüllung benötigen.</p>
<p>Die Geheimhaltungspflicht gilt nicht für Informationen, die öffentlich bekannt sind, unabhängig entwickelt wurden oder aufgrund gesetzlicher Verpflichtung offengelegt werden müssen.</p>
<p>Diese Pflicht besteht auch nach Vertragsende für einen Zeitraum von 3 Jahren fort.</p>
</div>

<h2>§ 11 Datenschutz</h2>
<div class="clause">
<p>Sofern im Rahmen der Entwicklung personenbezogene Daten verarbeitet werden, sind die Anforderungen der DSGVO einzuhalten. Soweit der Auftragnehmer im Auftrag des Auftraggebers personenbezogene Daten verarbeitet, wird ein gesonderter Auftragsverarbeitungsvertrag gemäß Art. 28 DSGVO geschlossen.</p>
</div>

<h2>§ 12 Haftung</h2>
<div class="clause">
<p>Der Auftragnehmer haftet unbeschränkt für Vorsatz und grobe Fahrlässigkeit sowie für Schäden an Leben, Körper oder Gesundheit.</p>
<p>Bei leichter Fahrlässigkeit haftet der Auftragnehmer nur bei Verletzung wesentlicher Vertragspflichten (Kardinalpflichten). Wesentliche Vertragspflichten sind solche, deren Erfüllung die ordnungsgemäße Durchführung des Vertrages überhaupt erst ermöglicht und auf deren Einhaltung der Vertragspartner regelmäßig vertrauen darf. In diesem Fall ist die Haftung auf den vorhersehbaren, vertragstypischen Schaden begrenzt.</p>
<p>Die Haftung ist auf die Höhe der vereinbarten Vergütung, mindestens jedoch 5.000 EUR, beschränkt. Dies gilt nicht für die in Absatz 1 genannten Fälle.</p>
</div>

<h2>§ 13 Höhere Gewalt</h2>
<div class="clause">
<p>Keine Partei haftet für die Nichterfüllung oder verzögerte Erfüllung ihrer Pflichten, soweit dies auf Umstände zurückzuführen ist, die außerhalb ihrer zumutbaren Kontrolle liegen (höhere Gewalt). Dazu zählen insbesondere Naturkatastrophen, Krieg, Pandemien, Streiks sowie behördliche Anordnungen.</p>
<p>Die betroffene Partei hat die andere Partei unverzüglich über den Eintritt und die voraussichtliche Dauer der höheren Gewalt zu informieren.</p>
</div>

<h2>§ 14 Schlussbestimmungen</h2>
<div class="clause">
<p><strong>B2B-Klausel:</strong> Dieser Vertrag richtet sich ausschließlich an Unternehmer im Sinne von § 14 BGB. Der Auftraggeber bestätigt, dass er diesen Vertrag im Rahmen seiner gewerblichen oder selbständigen beruflichen Tätigkeit abschließt.</p>
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}. Das Übereinkommen der Vereinten Nationen über Verträge über den internationalen Warenkauf (CISG) findet keine Anwendung.</p>
<p>Änderungen bedürfen der Schriftform. Dies gilt auch für die Änderung dieser Schriftformklausel.</p>
<p>Sollte eine Bestimmung dieses Vertrages unwirksam sein, so wird die Wirksamkeit der übrigen Bestimmungen hiervon nicht berührt. Die Parteien verpflichten sich, die unwirksame Bestimmung durch eine wirksame zu ersetzen, die dem wirtschaftlichen Zweck möglichst nahekommt.</p>
</div>
' WHERE id = 'tpl-dev-de';

-- 5. Updated Software Development Agreement (EN) — tpl-dev-en
UPDATE contract_templates SET content_html = '
<h2>1. Subject Matter</h2>
<div class="clause">
<p>The Developer agrees to develop the following software in accordance with the specifications set forth in this agreement:</p>
<p><strong>{{project_description}}</strong></p>
</div>

<h2>2. Scope and Milestones</h2>
<div class="clause">
<p>Development shall proceed according to the agreed milestones. The Developer owes a functional work in accordance with the agreed specifications (work contract pursuant to §§ 631 et seq. German Civil Code).</p>
{{#documentation_required}}<p><strong>Technical Documentation:</strong> The Developer shall deliver complete technical documentation consisting of API documentation, installation guide, and architecture overview. The documentation is part of the owed deliverable.</p>{{/documentation_required}}
{{#deployment_support}}<p><strong>Deployment Support:</strong> The Developer shall support the Client during the go-live of the software for a period of 5 business days after acceptance. This includes support with production deployment, configuration, and troubleshooting in the production environment.</p>{{/deployment_support}}
</div>

<h2>3. Client Cooperation Duties</h2>
<div class="clause">
<p>The Client is obligated to support the Developer to a reasonable extent in the performance of the contract. Cooperation duties include in particular:</p>
<ul>
<li>Timely provision of all information, data, and documents required for development</li>
<li>Designation of a qualified contact person with decision-making authority</li>
<li>Provision of required test environments and access</li>
<li>Timely feedback on presented interim results (within 5 business days)</li>
<li>Participation in acceptance testing pursuant to Section 5 of this agreement</li>
</ul>
<p>If the Client fails to fulfil its cooperation duties and this causes delays, the agreed deadlines shall be extended accordingly. Additional costs arising from insufficient cooperation shall be borne by the Client.</p>
</div>

<h2>4. Change Request Procedure</h2>
<div class="clause">
<p>Requests for changes that exceed the agreed scope of services must be submitted by the Client in writing as a Change Request. The Developer will provide an effort estimate with cost and timeline within 5 business days.</p>
<p>Change Requests shall only be implemented upon written approval by the Client. Until approval, development continues according to the original scope of services.</p>
</div>

<h2>5. Acceptance</h2>
<div class="clause">
<p>{{acceptance_procedure}}</p>
<p>The Client shall review and accept the software within 14 days of delivery, or report defects in writing. Acceptance is deemed granted if the Client uses the software productively without material objections or fails to respond in writing within the review period.</p>
<p>Minor defects do not entitle the Client to refuse acceptance.</p>
</div>

<h2>6. Compensation</h2>
<div class="clause">
<p>The total compensation amounts to <strong>{{total_value}} {{currency}}</strong>, payable {{payment_schedule_label}}.</p>
</div>

<h2>7. Intellectual Property</h2>
<div class="clause">
<p>Copyright in the developed software remains with the Developer.</p>
<p><strong>Purpose Transfer Rule (§ 31(5) German Copyright Act):</strong> The Client receives a non-exclusive, non-transferable license to use the software for its own business purposes. Usage rights not expressly granted remain with the Developer.</p>
<p>Source code shall be transferred to the Client upon full payment. Transfer of source code includes the right to inspect, correct errors, and adapt for own purposes, but not the right to transfer to third parties.</p>
<p><strong>Pre-existing Rights:</strong> Pre-existing software components of the Developer (frameworks, libraries, reusable modules) remain the property of the Developer. The Client receives a non-exclusive, perpetual license to use them within the scope of the developed software.</p>
</div>

<h2>8. Open Source Compliance</h2>
<div class="clause">
<p>The Developer shall inform the Client about the use of open-source components in the software. A list of open-source libraries used, including their respective license terms (e.g., MIT, Apache 2.0, GPL, LGPL), shall be provided as an appendix.</p>
<p>The Developer warrants that the use of open-source components does not create license obligations for the remaining software (in particular, no copyleft effect), unless expressly agreed otherwise.</p>
</div>

<h2>9. Warranty</h2>
<div class="clause">
<p>The warranty period is {{warranty_months}} months from acceptance. A defect exists if the software does not have the agreed quality or is not suitable for the contractually intended use.</p>
<p>The Developer is obligated to remedy defects. If remediation fails (after two attempts), the Client may request a reduction or rescission.</p>
</div>

<h2>10. Confidentiality</h2>
<div class="clause">
<p>Both parties undertake to keep confidential information of the other party strictly secret and to use it only for the purposes of this agreement. Confidential information includes in particular trade secrets within the meaning of the German Trade Secrets Act (GeschGehG), technical data, source code, customer data, and know-how.</p>
<p>The receiving party shall take appropriate protective measures and restrict access to employees who need the information for contract performance.</p>
<p>The confidentiality obligation does not apply to information that is publicly known, independently developed, or must be disclosed due to legal obligations.</p>
<p>This obligation survives for a period of 3 years after termination of this agreement.</p>
</div>

<h2>11. Data Protection</h2>
<div class="clause">
<p>If personal data is processed in the course of development, GDPR requirements must be observed. Where the Developer processes personal data on behalf of the Client, a separate Data Processing Agreement pursuant to Art. 28 GDPR shall be concluded.</p>
</div>

<h2>12. Limitation of Liability</h2>
<div class="clause">
<p>The Developer is liable without limitation for willful misconduct and gross negligence, and for damages to life, body, or health.</p>
<p>In cases of slight negligence, the Developer is liable only for breach of essential contractual obligations (cardinal obligations). Essential contractual obligations are those whose fulfilment is necessary for the proper performance of the contract and on whose compliance the contractual partner may regularly rely. In such cases, liability is limited to the foreseeable, contract-typical damage.</p>
<p>Liability is limited to the agreed compensation, but no less than EUR 5,000. This does not apply to the cases mentioned in paragraph 1.</p>
</div>

<h2>13. Force Majeure</h2>
<div class="clause">
<p>Neither party shall be liable for non-performance or delayed performance of its obligations to the extent caused by circumstances beyond its reasonable control (force majeure), including natural disasters, war, pandemics, strikes, and governmental orders.</p>
<p>The affected party shall promptly notify the other party of the occurrence and expected duration of the force majeure event.</p>
</div>

<h2>14. General Provisions</h2>
<div class="clause">
<p><strong>B2B Clause:</strong> This agreement is directed exclusively at entrepreneurs within the meaning of § 14 German Civil Code (BGB). The Client confirms that it is entering into this agreement in the course of its commercial or independent professional activity.</p>
<p>This agreement is governed by the laws of {{governing_law_label}}. Jurisdiction is {{jurisdiction}}. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.</p>
<p>Amendments require written form. This also applies to any waiver of this written form requirement.</p>
<p>If any provision of this agreement is or becomes invalid, the validity of the remaining provisions shall not be affected. The parties undertake to replace the invalid provision with a valid provision that most closely achieves the economic purpose of the invalid provision.</p>
</div>
' WHERE id = 'tpl-dev-en';

-- 6. Updated SaaS-Vertrag (DE) — tpl-saas-de
UPDATE contract_templates SET content_html = '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Anbieter stellt dem Kunden folgenden Cloud-basierten Softwaredienst (SaaS) zur Verfügung:</p>
<p><strong>{{service_description}}</strong></p>
</div>

<h2>§ 2 Leistungsumfang</h2>
<div class="clause">
<p>Der Dienst umfasst die Bereitstellung der Software über das Internet mit folgenden Parametern:</p>
<ul>
<li>Maximale Nutzeranzahl: {{max_users}} (0 = unbegrenzt)</li>
<li>Speicherplatz: {{storage_gb}} GB</li>
<li>Datenstandort: {{data_location_label}}</li>
{{#support_included}}<li>Support-Level: {{support_level_label}}</li>{{/support_included}}
</ul>
</div>

<h2>§ 3 Verfügbarkeit (SLA)</h2>
<div class="clause">
<p>Der Anbieter garantiert eine Verfügbarkeit von <strong>{{sla_uptime}}%</strong> im Monatsmittel, gemessen außerhalb geplanter Wartungsfenster. Geplante Wartungsarbeiten werden mindestens 48 Stunden im Voraus angekündigt.</p>
{{#sla_credit}}<p><strong>SLA-Gutschriften:</strong> Unterschreitet der Anbieter die garantierte Verfügbarkeit in einem Kalendermonat, erhält der Kunde eine anteilige Gutschrift auf die Nutzungsgebühr des betroffenen Monats. Die Gutschrift berechnet sich anteilig zur tatsächlichen Ausfallzeit. Das Messverfahren basiert auf dem monatlichen Durchschnitt; geplante Wartungsarbeiten sind ausgenommen.</p>
<p>Ab einer Verfügbarkeit unter 95% im Monatsmittel ist der Kunde zur außerordentlichen Kündigung berechtigt.</p>{{/sla_credit}}
{{^sla_credit}}<p>Unterschreitet der Anbieter die garantierte Verfügbarkeit erheblich in einem Kalendermonat, ist der Kunde zur außerordentlichen Kündigung berechtigt.</p>{{/sla_credit}}
</div>

<h2>§ 4 Gewährleistung</h2>
<div class="clause">
<p>Der Anbieter gewährleistet, dass der Dienst im Wesentlichen der Leistungsbeschreibung entspricht. Ein Mangel liegt vor, wenn der Dienst nicht die vereinbarte Beschaffenheit aufweist oder sich nicht für die vertraglich vorausgesetzte Verwendung eignet.</p>
<p>{{#is_b2c}}Die Gewährleistungsfrist beträgt 24 Monate ab Bereitstellung (§ 327j BGB). Für Mängel, die sich innerhalb von 12 Monaten nach Bereitstellung zeigen, wird vermutet, dass sie bereits bei Bereitstellung vorlagen (§ 327k BGB).{{/is_b2c}}{{^is_b2c}}Die Gewährleistungsfrist beträgt {{warranty_duration_months}} Monate ab Bereitstellung.{{/is_b2c}}</p>
<p>Der Anbieter ist zur Nachbesserung verpflichtet. Schlägt die Nachbesserung nach zwei Versuchen fehl, kann der Kunde Minderung oder Kündigung verlangen.</p>
</div>

{{#is_b2c}}<h2>§ 5 Aktualisierungen (Verbraucher)</h2>
<div class="clause">
<p><strong>Updatepflicht (§ 327f BGB):</strong> Der Anbieter stellt während der gesamten Vertragslaufzeit Aktualisierungen bereit, die für den Erhalt der Vertragsmäßigkeit des digitalen Produkts erforderlich sind, einschließlich Sicherheitsaktualisierungen.</p>
<p>Der Anbieter informiert den Verbraucher über verfügbare Aktualisierungen und die Folgen einer unterlassenen Installation.</p>
<p><strong>Änderungsvorbehalt (§ 327r BGB):</strong> Änderungen am Dienst, die über das zur Aufrechterhaltung der Vertragsmäßigkeit Erforderliche hinausgehen, dürfen nur vorgenommen werden, wenn der Vertrag dies vorsieht und ein triftiger Grund vorliegt. Der Verbraucher wird über geplante Änderungen vorab informiert.</p>
</div>{{/is_b2c}}

<h2>Datenschutz und Auftragsverarbeitung</h2>
<div class="clause">
<p>Die Verarbeitung personenbezogener Daten erfolgt gemäß DSGVO. Ein separater Auftragsverarbeitungsvertrag (AVV) gemäß Art. 28 DSGVO ist Bestandteil dieses Vertrages und enthält mindestens folgende Regelungen:</p>
<ul>
<li><strong>Weisungsbindung:</strong> Der Anbieter verarbeitet personenbezogene Daten ausschließlich auf dokumentierte Weisung des Kunden (Art. 28 Abs. 3 lit. a DSGVO)</li>
<li><strong>Vertraulichkeit:</strong> Zur Verarbeitung befugte Personen sind zur Vertraulichkeit verpflichtet (Art. 28 Abs. 3 lit. b DSGVO)</li>
<li><strong>Technische und organisatorische Maßnahmen (TOM):</strong> Der Anbieter trifft angemessene TOM gemäß Art. 32 DSGVO, insbesondere Verschlüsselung, Zugangskontrollen, Backup-Verfahren und regelmäßige Sicherheitsprüfungen</li>
<li><strong>Subunternehmer:</strong> Die Beauftragung von Subunternehmern bedarf der vorherigen schriftlichen Zustimmung des Kunden. Der Anbieter führt eine aktuelle Liste der eingesetzten Subunternehmer (Art. 28 Abs. 2 DSGVO)</li>
<li><strong>Kontrollrechte:</strong> Der Kunde hat das Recht, die Einhaltung der technischen und organisatorischen Maßnahmen zu überprüfen (Art. 28 Abs. 3 lit. h DSGVO)</li>
<li><strong>Meldung von Datenschutzverletzungen:</strong> Der Anbieter meldet Datenschutzverletzungen unverzüglich, spätestens jedoch innerhalb von 72 Stunden nach Bekanntwerden (Art. 33 DSGVO)</li>
<li><strong>Löschpflichten:</strong> Nach Vertragsende werden personenbezogene Daten gemäß Art. 28 Abs. 3 lit. g DSGVO gelöscht oder zurückgegeben</li>
</ul>
<p>Daten werden ausschließlich in {{data_location_label}} gespeichert.</p>
</div>

<h2>Vergütung</h2>
<div class="clause">
<p>Die Nutzungsgebühr beträgt <strong>{{price_per_period}} {{currency}}</strong> pro {{subscription_model_label}} und ist im Voraus fällig.</p>
{{^is_b2c}}<p><strong>Zahlungsverzug:</strong> Im Falle des Zahlungsverzugs fallen Verzugszinsen in Höhe von 9 Prozentpunkten über dem Basiszinssatz an (§ 288 Abs. 2 BGB). Zusätzlich wird eine Mahnpauschale von 40 EUR (§ 288 Abs. 5 BGB) erhoben.</p>{{/is_b2c}}
{{#is_b2c}}<p><strong>Zahlungsverzug:</strong> Im Falle des Zahlungsverzugs fallen Verzugszinsen in Höhe von 5 Prozentpunkten über dem Basiszinssatz an (§ 288 Abs. 1 BGB).</p>{{/is_b2c}}
</div>

<h2>Laufzeit und Kündigung</h2>
<div class="clause">
<p>Der Vertrag beginnt am {{start_date}} und hat eine Laufzeit von jeweils einem {{subscription_model_label}}. Die Kündigungsfrist beträgt {{notice_period_days}} Tage zum Ende der jeweiligen Abrechnungsperiode.</p>
<p>{{#auto_renewal}}Der Vertrag verlängert sich automatisch um jeweils einen weiteren Abrechnungszeitraum, sofern er nicht fristgerecht gekündigt wird.{{/auto_renewal}}</p>
<p>Das Recht zur außerordentlichen Kündigung aus wichtigem Grund (§ 314 BGB) bleibt unberührt.</p>
</div>

<h2>Datenexport und Vertragsende</h2>
<div class="clause">
<p>Bei Vertragsende stellt der Anbieter dem Kunden seine Daten in einem gängigen, maschinenlesbaren Format (CSV, JSON oder XML) für den Export zur Verfügung. Der Exportzeitraum beträgt 30 Tage nach Vertragsende. Nach Ablauf dieses Zeitraums werden die Daten unwiderruflich gelöscht.</p>
</div>

<h2>Haftung</h2>
<div class="clause">
<p>Der Anbieter haftet unbeschränkt für Vorsatz und grobe Fahrlässigkeit sowie für Schäden an Leben, Körper oder Gesundheit.</p>
<p>Bei leichter Fahrlässigkeit haftet der Anbieter nur bei Verletzung wesentlicher Vertragspflichten (Kardinalpflichten). Wesentliche Vertragspflichten sind solche, deren Erfüllung die ordnungsgemäße Durchführung des Vertrages überhaupt erst ermöglicht und auf deren Einhaltung der Vertragspartner regelmäßig vertrauen darf. In diesem Fall ist die Haftung auf den vorhersehbaren, vertragstypischen Schaden begrenzt.</p>
<p>Die Haftung ist auf die in den letzten 12 Monaten gezahlten Nutzungsgebühren, mindestens jedoch 5.000 EUR, beschränkt. Dies gilt nicht für die in Absatz 1 genannten Fälle.</p>
</div>

<h2>Höhere Gewalt (Force Majeure)</h2>
<div class="clause">
<p>Keine Partei haftet für die Nichterfüllung oder verzögerte Erfüllung ihrer Pflichten, soweit dies auf Umstände zurückzuführen ist, die außerhalb ihrer zumutbaren Kontrolle liegen (höhere Gewalt). Dazu zählen insbesondere Naturkatastrophen, Krieg, Terrorismus, Pandemien, Streiks, behördliche Anordnungen sowie Ausfall wesentlicher Infrastruktur (Strom, Internet, Rechenzentren).</p>
<p>Die betroffene Partei hat die andere Partei unverzüglich über den Eintritt und die voraussichtliche Dauer der höheren Gewalt zu informieren. Dauert der Zustand der höheren Gewalt länger als 30 Tage an, ist jede Partei berechtigt, den Vertrag außerordentlich zu kündigen.</p>
</div>

{{#is_b2c}}<h2>Widerrufsbelehrung (Verbraucher)</h2>
<div class="clause">
<p><strong>Widerrufsrecht</strong></p>
<p>Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag des Vertragsschlusses. Um Ihr Widerrufsrecht auszuüben, müssen Sie uns ({{party_a_company}}, {{party_a_address}}, E-Mail: {{party_a_email}}) mittels einer eindeutigen Erklärung (z.B. ein mit der Post versandter Brief oder E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.</p>
<p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p>
<p><strong>Folgen des Widerrufs</strong></p>
<p>Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist.</p>
<p><strong>Besonderer Hinweis bei digitalen Inhalten (§ 356 Abs. 5 BGB):</strong> Sie stimmen ausdrücklich zu, dass wir mit der Ausführung des Vertrages vor Ablauf der Widerrufsfrist beginnen. Sie haben Kenntnis davon, dass Sie mit Beginn der Ausführung des Vertrages Ihr Widerrufsrecht verlieren.</p>
</div>{{/is_b2c}}

<h2>Schlussbestimmungen</h2>
<div class="clause">
{{^is_b2c}}<p><strong>B2B-Klausel:</strong> Dieser Vertrag richtet sich ausschließlich an Unternehmer im Sinne von § 14 BGB. Der Kunde bestätigt, dass er diesen Vertrag im Rahmen seiner gewerblichen oder selbständigen beruflichen Tätigkeit abschließt.</p>{{/is_b2c}}
<p>Es gilt das Recht der {{governing_law_label}}. Das Übereinkommen der Vereinten Nationen über Verträge über den internationalen Warenkauf (CISG) findet keine Anwendung.</p>
<p>{{^is_b2c}}Gerichtsstand ist {{jurisdiction}}.{{/is_b2c}}{{#is_b2c}}Für Verbraucher gilt der gesetzliche Gerichtsstand.{{/is_b2c}}</p>
<p>Änderungen und Ergänzungen dieses Vertrages bedürfen der Schriftform. Dies gilt auch für die Änderung dieser Schriftformklausel.</p>
<p>Sollte eine Bestimmung dieses Vertrages unwirksam sein, so wird die Wirksamkeit der übrigen Bestimmungen hiervon nicht berührt. Die Parteien verpflichten sich, die unwirksame Bestimmung durch eine wirksame zu ersetzen, die dem wirtschaftlichen Zweck möglichst nahekommt.</p>
</div>
' WHERE id = 'tpl-saas-de';

-- 7. Updated SaaS Agreement (EN) — tpl-saas-en
UPDATE contract_templates SET content_html = '
<h2>1. Subject Matter</h2>
<div class="clause">
<p>The Provider makes the following cloud-based software service (SaaS) available to the Customer:</p>
<p><strong>{{service_description}}</strong></p>
</div>

<h2>2. Scope of Service</h2>
<div class="clause">
<p>The service includes access to the software via the internet with the following parameters:</p>
<ul>
<li>Maximum users: {{max_users}} (0 = unlimited)</li>
<li>Storage: {{storage_gb}} GB</li>
<li>Data location: {{data_location_label}}</li>
{{#support_included}}<li>Support Level: {{support_level_label}}</li>{{/support_included}}
</ul>
</div>

<h2>3. Availability (SLA)</h2>
<div class="clause">
<p>The Provider guarantees an uptime of <strong>{{sla_uptime}}%</strong> on a monthly average, excluding scheduled maintenance windows. Planned maintenance will be announced at least 48 hours in advance.</p>
{{#sla_credit}}<p><strong>SLA Credits:</strong> If the Provider falls below the guaranteed availability in a calendar month, the Customer shall receive a proportional credit on the usage fee for the affected month. The credit is calculated proportionally to the actual downtime. Measurement is based on monthly average; planned maintenance is excluded.</p>
<p>If availability falls below 95% in a monthly average, the Customer is entitled to extraordinary termination.</p>{{/sla_credit}}
{{^sla_credit}}<p>If the Provider significantly falls below the guaranteed availability in a calendar month, the Customer is entitled to extraordinary termination.</p>{{/sla_credit}}
</div>

<h2>4. Warranty</h2>
<div class="clause">
<p>The Provider warrants that the service substantially conforms to the service description. A defect exists if the service does not have the agreed quality or is not suitable for the contractually intended use.</p>
<p>{{#is_b2c}}The warranty period is 24 months from provision (§ 327j German Civil Code). For defects appearing within 12 months of provision, it is presumed that they existed at the time of provision (§ 327k German Civil Code).{{/is_b2c}}{{^is_b2c}}The warranty period is {{warranty_duration_months}} months from provision.{{/is_b2c}}</p>
<p>The Provider is obligated to remedy defects. If remediation fails (after two attempts), the Customer may request a reduction or termination.</p>
</div>

{{#is_b2c}}<h2>5. Updates (Consumers)</h2>
<div class="clause">
<p><strong>Update Obligation (§ 327f German Civil Code):</strong> The Provider shall make available updates necessary to maintain the conformity of the digital product throughout the entire contract term, including security updates.</p>
<p>The Provider shall inform the consumer of available updates and the consequences of not installing them.</p>
<p><strong>Modification Reservation (§ 327r German Civil Code):</strong> Modifications to the service beyond those necessary to maintain conformity may only be made if the contract provides for this and there is a legitimate reason. The consumer will be informed in advance of planned modifications.</p>
</div>{{/is_b2c}}

<h2>Data Protection and Data Processing</h2>
<div class="clause">
<p>Processing of personal data is carried out in accordance with GDPR. A separate Data Processing Agreement (DPA) pursuant to Art. 28 GDPR forms part of this agreement and contains at minimum the following provisions:</p>
<ul>
<li><strong>Instruction Binding:</strong> The Provider processes personal data exclusively based on documented instructions from the Customer (Art. 28(3)(a) GDPR)</li>
<li><strong>Confidentiality:</strong> Persons authorized to process data are bound by confidentiality (Art. 28(3)(b) GDPR)</li>
<li><strong>Technical and Organizational Measures (TOM):</strong> The Provider implements appropriate TOM pursuant to Art. 32 GDPR, including encryption, access controls, backup procedures, and regular security audits</li>
<li><strong>Sub-processors:</strong> Engagement of sub-processors requires prior written consent from the Customer. The Provider maintains a current list of sub-processors (Art. 28(2) GDPR)</li>
<li><strong>Audit Rights:</strong> The Customer has the right to verify compliance with technical and organizational measures (Art. 28(3)(h) GDPR)</li>
<li><strong>Data Breach Notification:</strong> The Provider shall report data breaches without undue delay, no later than 72 hours after becoming aware (Art. 33 GDPR)</li>
<li><strong>Deletion Obligations:</strong> After contract termination, personal data shall be deleted or returned pursuant to Art. 28(3)(g) GDPR</li>
</ul>
<p>Data is stored exclusively in {{data_location_label}}.</p>
</div>

<h2>Fees</h2>
<div class="clause">
<p>The usage fee amounts to <strong>{{price_per_period}} {{currency}}</strong> per {{subscription_model_label}}, payable in advance.</p>
{{^is_b2c}}<p><strong>Late Payment:</strong> In case of late payment, default interest of 9 percentage points above the base interest rate applies (§ 288(2) German Civil Code). Additionally, a flat-rate dunning fee of EUR 40 (§ 288(5) German Civil Code) will be charged.</p>{{/is_b2c}}
{{#is_b2c}}<p><strong>Late Payment:</strong> In case of late payment, default interest of 5 percentage points above the base interest rate applies (§ 288(1) German Civil Code).</p>{{/is_b2c}}
</div>

<h2>Term and Termination</h2>
<div class="clause">
<p>This agreement commences on {{start_date}} and has a term of one {{subscription_model_label}} each. The notice period is {{notice_period_days}} days before the end of the respective billing period.</p>
<p>{{#auto_renewal}}The agreement automatically renews for an additional billing period unless terminated in due time.{{/auto_renewal}}</p>
<p>The right to extraordinary termination for cause (§ 314 German Civil Code) remains unaffected.</p>
</div>

<h2>Data Export and Termination</h2>
<div class="clause">
<p>Upon termination, the Provider shall make the Customer''s data available for export in a common, machine-readable format (CSV, JSON, or XML). The export period is 30 days after contract termination. After expiry of this period, data will be irrevocably deleted.</p>
</div>

<h2>Limitation of Liability</h2>
<div class="clause">
<p>The Provider is liable without limitation for willful misconduct and gross negligence, and for damages to life, body, or health.</p>
<p>In cases of slight negligence, the Provider is liable only for breach of essential contractual obligations (cardinal obligations). Essential contractual obligations are those whose fulfilment is necessary for the proper performance of the contract and on whose compliance the contractual partner may regularly rely. In such cases, liability is limited to the foreseeable, contract-typical damage.</p>
<p>Liability is limited to the fees paid in the preceding 12 months, but no less than EUR 5,000. This does not apply to the cases mentioned in paragraph 1.</p>
</div>

<h2>Force Majeure</h2>
<div class="clause">
<p>Neither party shall be liable for non-performance or delayed performance of its obligations to the extent caused by circumstances beyond its reasonable control (force majeure). This includes in particular natural disasters, war, terrorism, pandemics, strikes, governmental orders, and failure of essential infrastructure (power, internet, data centers).</p>
<p>The affected party shall promptly notify the other party of the occurrence and expected duration of the force majeure event. If the force majeure event persists for more than 30 days, either party is entitled to terminate the agreement for cause.</p>
</div>

{{#is_b2c}}<h2>Right of Withdrawal (Consumers)</h2>
<div class="clause">
<p><strong>Right of Withdrawal</strong></p>
<p>You have the right to withdraw from this contract within fourteen days without giving any reason. The withdrawal period is fourteen days from the date of conclusion of the contract. To exercise your right of withdrawal, you must inform us ({{party_a_company}}, {{party_a_address}}, email: {{party_a_email}}) of your decision to withdraw from this contract by means of a clear declaration (e.g. a letter sent by post or email).</p>
<p>To comply with the withdrawal period, it is sufficient to send the notification of the exercise of the right of withdrawal before the withdrawal period expires.</p>
<p><strong>Consequences of Withdrawal</strong></p>
<p>If you withdraw from this contract, we shall reimburse all payments received from you without undue delay and at the latest within fourteen days from the day on which we receive the notification of your withdrawal.</p>
<p><strong>Special Notice for Digital Content (§ 356(5) German Civil Code):</strong> You expressly agree that we may begin performance of the contract before the withdrawal period expires. You acknowledge that you will lose your right of withdrawal upon commencement of contract performance.</p>
</div>{{/is_b2c}}

<h2>General Provisions</h2>
<div class="clause">
{{^is_b2c}}<p><strong>B2B Clause:</strong> This agreement is directed exclusively at entrepreneurs within the meaning of § 14 German Civil Code (BGB). The Customer confirms that it is entering into this agreement in the course of its commercial or independent professional activity.</p>{{/is_b2c}}
<p>This agreement is governed by the laws of {{governing_law_label}}. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.</p>
<p>{{^is_b2c}}Jurisdiction is {{jurisdiction}}.{{/is_b2c}}{{#is_b2c}}For consumers, the statutory place of jurisdiction applies.{{/is_b2c}}</p>
<p>Amendments require written form. This also applies to any waiver of this written form requirement.</p>
<p>If any provision of this agreement is or becomes invalid, the validity of the remaining provisions shall not be affected. The parties undertake to replace the invalid provision with a valid provision that most closely achieves the economic purpose of the invalid provision.</p>
</div>
' WHERE id = 'tpl-saas-en';

-- 8. Updated Wartungsvertrag (DE) — tpl-maint-de
UPDATE contract_templates SET content_html = '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Auftragnehmer übernimmt die Wartung und den Support für folgende Software:</p>
<p><strong>{{maintained_software}}</strong></p>
</div>

<h2>§ 2 Leistungsumfang</h2>
<div class="clause">
<p>Der Wartungsvertrag umfasst:</p>
<ul>
<li>Support-Kontingent: {{support_hours_monthly}} Stunden pro Monat</li>
<li>Reaktionszeit: {{response_time_label}}</li>
{{#included_patches}}<li>Sicherheits-Patches und Bugfixes</li>{{/included_patches}}
{{#included_minor_updates}}<li>Minor Updates (Funktionserweiterungen)</li>{{/included_minor_updates}}
{{#included_major_updates}}<li>Major Updates (neue Hauptversionen)</li>{{/included_major_updates}}
</ul>
{{#remote_access_required}}<p>Für die Wartung ist ein Remote-Zugang zum System des Auftraggebers erforderlich.</p>{{/remote_access_required}}
</div>

<h2>§ 3 Reaktionszeiten</h2>
<div class="clause">
<p>Der Auftragnehmer reagiert innerhalb der vereinbarten Reaktionszeit von <strong>{{response_time_label}}</strong> auf Supportanfragen während der Geschäftszeiten (Mo-Fr 9:00-17:00 Uhr).</p>
{{#emergency_support}}<p><strong>24/7 Notfall-Support:</strong> Für kritische Ausfälle (Totalausfall des Systems) ist der Auftragnehmer rund um die Uhr erreichbar. Die Einstufung der Störung erfolgt nach folgendem Schema:</p>
<ul>
<li><strong>Kritisch (Totalausfall):</strong> Reaktionszeit {{response_time_label}} — auch außerhalb der Geschäftszeiten</li>
<li><strong>Hoch (eingeschränkte Funktion):</strong> Doppelte Reaktionszeit</li>
<li><strong>Normal (Komfort-Einschränkung):</strong> Nächster Werktag</li>
</ul>{{/emergency_support}}
</div>

<h2>§ 4 Eskalationsverfahren</h2>
<div class="clause">
<p>Bei Störungen, die nicht innerhalb der vereinbarten Reaktionszeit behoben werden können, gilt folgendes Eskalationsverfahren:</p>
<ul>
<li><strong>Stufe 1 — Technischer Support:</strong> Erstbearbeitung durch den zuständigen Support-Mitarbeiter innerhalb der vereinbarten Reaktionszeit</li>
<li><strong>Stufe 2 — Projektleitung:</strong> Eskalation an die Projektleitung, wenn nach dem Doppelten der Reaktionszeit keine Lösung vorliegt. Bereitstellung eines Workarounds oder Zeitplans für die Behebung</li>
<li><strong>Stufe 3 — Geschäftsführung:</strong> Eskalation an die Geschäftsführung beider Parteien, wenn nach 48 Stunden keine Lösung oder akzeptabler Workaround vorliegt</li>
</ul>
</div>

<h2>§ 5 Gewährleistung</h2>
<div class="clause">
<p>Der Auftragnehmer gewährleistet die fachgerechte Durchführung der Wartungsarbeiten. Für Mängel, die durch eine Wartungsmaßnahme verursacht werden, haftet der Auftragnehmer im Rahmen der Gewährleistung und ist zur unverzüglichen Nachbesserung verpflichtet.</p>
</div>

<h2>§ 6 Vergütung</h2>
<div class="clause">
<p>Die Wartungsgebühr beträgt <strong>{{total_value}} {{currency}}</strong> und wird {{payment_schedule_label}} abgerechnet. Leistungen über das vereinbarte Kontingent hinaus werden nach Aufwand abgerechnet.</p>
</div>

<h2>§ 7 Laufzeit und Kündigung</h2>
<div class="clause">
<p>Der Vertrag beginnt am {{start_date}} und läuft auf unbestimmte Zeit. Die Kündigungsfrist beträgt {{notice_period_days}} Tage zum Monatsende.</p>
<p>Das Recht zur außerordentlichen Kündigung aus wichtigem Grund (§ 314 BGB) bleibt unberührt.</p>
</div>

<h2>§ 8 Vertraulichkeit</h2>
<div class="clause">
<p>Der Auftragnehmer verpflichtet sich, alle im Rahmen der Wartung erlangten Informationen, Daten und Zugangsdaten des Auftraggebers streng vertraulich zu behandeln. Diese Verpflichtung umfasst insbesondere Geschäftsgeheimnisse im Sinne des GeschGehG, Kundendaten, technische Konfigurationen und Systemzugänge.</p>
<p>Der Zugang wird nur Mitarbeitern gewährt, die zur Vertragserfüllung erforderlich sind und einer gleichwertigen Vertraulichkeitspflicht unterliegen.</p>
<p>Diese Pflicht besteht auch nach Vertragsende fort.</p>
</div>

<h2>§ 9 Datenschutz</h2>
<div class="clause">
<p>Sofern der Auftragnehmer im Rahmen der Wartung Zugang zu Systemen erhält, die personenbezogene Daten enthalten, sind die Anforderungen der DSGVO einzuhalten. Soweit erforderlich, wird ein gesonderter Auftragsverarbeitungsvertrag gemäß Art. 28 DSGVO geschlossen.</p>
<p>Der Auftragnehmer wird personenbezogene Daten, die ihm im Rahmen der Wartung zugänglich werden, nicht über das zur Vertragserfüllung erforderliche Maß hinaus verarbeiten.</p>
</div>

<h2>§ 10 Haftung</h2>
<div class="clause">
<p>Der Auftragnehmer haftet unbeschränkt für Vorsatz und grobe Fahrlässigkeit sowie für Schäden an Leben, Körper oder Gesundheit.</p>
<p>Bei leichter Fahrlässigkeit haftet der Auftragnehmer nur bei Verletzung wesentlicher Vertragspflichten (Kardinalpflichten). Wesentliche Vertragspflichten sind solche, deren Erfüllung die ordnungsgemäße Durchführung des Vertrages überhaupt erst ermöglicht und auf deren Einhaltung der Vertragspartner regelmäßig vertrauen darf. In diesem Fall ist die Haftung auf den vorhersehbaren, vertragstypischen Schaden begrenzt.</p>
<p>Die Haftung ist auf die jährliche Wartungsgebühr, mindestens jedoch 5.000 EUR, beschränkt. Dies gilt nicht für die in Absatz 1 genannten Fälle.</p>
</div>

<h2>§ 11 Höhere Gewalt</h2>
<div class="clause">
<p>Keine Partei haftet für die Nichterfüllung oder verzögerte Erfüllung ihrer Pflichten, soweit dies auf Umstände zurückzuführen ist, die außerhalb ihrer zumutbaren Kontrolle liegen (höhere Gewalt). Dazu zählen insbesondere Naturkatastrophen, Krieg, Pandemien, Streiks sowie behördliche Anordnungen.</p>
<p>Die betroffene Partei hat die andere Partei unverzüglich über den Eintritt und die voraussichtliche Dauer der höheren Gewalt zu informieren.</p>
</div>

<h2>§ 12 Schlussbestimmungen</h2>
<div class="clause">
<p><strong>B2B-Klausel:</strong> Dieser Vertrag richtet sich ausschließlich an Unternehmer im Sinne von § 14 BGB. Der Auftraggeber bestätigt, dass er diesen Vertrag im Rahmen seiner gewerblichen oder selbständigen beruflichen Tätigkeit abschließt.</p>
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}. Das Übereinkommen der Vereinten Nationen über Verträge über den internationalen Warenkauf (CISG) findet keine Anwendung.</p>
<p>Änderungen bedürfen der Schriftform. Dies gilt auch für die Änderung dieser Schriftformklausel.</p>
<p>Sollte eine Bestimmung dieses Vertrages unwirksam sein, so wird die Wirksamkeit der übrigen Bestimmungen hiervon nicht berührt. Die Parteien verpflichten sich, die unwirksame Bestimmung durch eine wirksame zu ersetzen, die dem wirtschaftlichen Zweck möglichst nahekommt.</p>
</div>
' WHERE id = 'tpl-maint-de';

-- 9. Updated Maintenance Agreement (EN) — tpl-maint-en
UPDATE contract_templates SET content_html = '
<h2>1. Subject Matter</h2>
<div class="clause">
<p>The Provider assumes maintenance and support for the following software:</p>
<p><strong>{{maintained_software}}</strong></p>
</div>

<h2>2. Scope of Services</h2>
<div class="clause">
<p>The maintenance agreement includes:</p>
<ul>
<li>Support hours: {{support_hours_monthly}} hours per month</li>
<li>Response time: {{response_time_label}}</li>
{{#included_patches}}<li>Security patches and bug fixes</li>{{/included_patches}}
{{#included_minor_updates}}<li>Minor updates (feature enhancements)</li>{{/included_minor_updates}}
{{#included_major_updates}}<li>Major updates (new major versions)</li>{{/included_major_updates}}
</ul>
{{#remote_access_required}}<p>Remote access to the Client''s system is required for maintenance.</p>{{/remote_access_required}}
</div>

<h2>3. Response Times</h2>
<div class="clause">
<p>The Provider responds within <strong>{{response_time_label}}</strong> to support requests during business hours (Mon-Fri 9:00 AM - 5:00 PM).</p>
{{#emergency_support}}<p><strong>24/7 Emergency Support:</strong> For critical failures (complete system outage), the Provider is available around the clock. Incident classification follows this scheme:</p>
<ul>
<li><strong>Critical (complete outage):</strong> Response time {{response_time_label}} — including outside business hours</li>
<li><strong>High (limited functionality):</strong> Twice the response time</li>
<li><strong>Normal (comfort restriction):</strong> Next business day</li>
</ul>{{/emergency_support}}
</div>

<h2>4. Escalation Procedure</h2>
<div class="clause">
<p>For issues that cannot be resolved within the agreed response time, the following escalation procedure applies:</p>
<ul>
<li><strong>Level 1 — Technical Support:</strong> Initial handling by the assigned support engineer within the agreed response time</li>
<li><strong>Level 2 — Project Management:</strong> Escalation to project management if no resolution is available after twice the response time. Provision of a workaround or remediation timeline</li>
<li><strong>Level 3 — Executive Management:</strong> Escalation to executive management of both parties if no resolution or acceptable workaround is available after 48 hours</li>
</ul>
</div>

<h2>5. Warranty</h2>
<div class="clause">
<p>The Provider warrants the professional performance of maintenance services. For defects caused by a maintenance measure, the Provider is liable under warranty and obligated to remedy them without undue delay.</p>
</div>

<h2>6. Fees</h2>
<div class="clause">
<p>The maintenance fee amounts to <strong>{{total_value}} {{currency}}</strong>, payable {{payment_schedule_label}}. Services beyond the agreed scope are billed at hourly rates.</p>
</div>

<h2>7. Term and Termination</h2>
<div class="clause">
<p>This agreement commences on {{start_date}} and continues indefinitely. The notice period is {{notice_period_days}} days to the end of the month.</p>
<p>The right to extraordinary termination for cause (§ 314 German Civil Code) remains unaffected.</p>
</div>

<h2>8. Confidentiality</h2>
<div class="clause">
<p>The Provider undertakes to treat all information, data, and access credentials of the Client obtained in the course of maintenance as strictly confidential. This obligation covers in particular trade secrets within the meaning of the German Trade Secrets Act (GeschGehG), customer data, technical configurations, and system access credentials.</p>
<p>Access shall be granted only to employees required for contract performance who are subject to equivalent confidentiality obligations.</p>
<p>This obligation survives termination of this agreement.</p>
</div>

<h2>9. Data Protection</h2>
<div class="clause">
<p>If the Provider gains access to systems containing personal data in the course of maintenance, GDPR requirements must be observed. Where necessary, a separate Data Processing Agreement pursuant to Art. 28 GDPR shall be concluded.</p>
<p>The Provider shall not process personal data accessed during maintenance beyond the extent required for contract performance.</p>
</div>

<h2>10. Limitation of Liability</h2>
<div class="clause">
<p>The Provider is liable without limitation for willful misconduct and gross negligence, and for damages to life, body, or health.</p>
<p>In cases of slight negligence, the Provider is liable only for breach of essential contractual obligations (cardinal obligations). Essential contractual obligations are those whose fulfilment is necessary for the proper performance of the contract and on whose compliance the contractual partner may regularly rely. In such cases, liability is limited to the foreseeable, contract-typical damage.</p>
<p>Liability is limited to the annual maintenance fee, but no less than EUR 5,000. This does not apply to the cases mentioned in paragraph 1.</p>
</div>

<h2>11. Force Majeure</h2>
<div class="clause">
<p>Neither party shall be liable for non-performance or delayed performance of its obligations to the extent caused by circumstances beyond its reasonable control (force majeure), including natural disasters, war, pandemics, strikes, and governmental orders.</p>
<p>The affected party shall promptly notify the other party of the occurrence and expected duration of the force majeure event.</p>
</div>

<h2>12. General Provisions</h2>
<div class="clause">
<p><strong>B2B Clause:</strong> This agreement is directed exclusively at entrepreneurs within the meaning of § 14 German Civil Code (BGB). The Client confirms that it is entering into this agreement in the course of its commercial or independent professional activity.</p>
<p>This agreement is governed by the laws of {{governing_law_label}}. Jurisdiction is {{jurisdiction}}. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.</p>
<p>Amendments require written form. This also applies to any waiver of this written form requirement.</p>
<p>If any provision of this agreement is or becomes invalid, the validity of the remaining provisions shall not be affected. The parties undertake to replace the invalid provision with a valid provision that most closely achieves the economic purpose of the invalid provision.</p>
</div>
' WHERE id = 'tpl-maint-en';

-- 10. NEW Source-Code-Kaufvertrag (DE) — tpl-scp-de
INSERT INTO contract_templates (id, contract_type, language, name, content_html) VALUES ('tpl-scp-de', 'source_code_purchase', 'de', 'Source-Code-Kaufvertrag', '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Verkäufer veräußert und überträgt an den Käufer den Quellcode der Software <strong>{{software_name}}</strong> (Version {{software_version}}) einschließlich der zugehörigen Rechte gemäß den Bestimmungen dieses Vertrages.</p>
<p>Umfang des Quellcodes: <strong>{{source_code_scope}}</strong></p>
</div>

<h2>§ 2 Rechteübertragung</h2>
<div class="clause">
<p>Art der Rechteübertragung: <strong>{{ip_transfer_type_label}}</strong></p>
<p>Die Übertragung umfasst sämtliche für die Nutzung, Bearbeitung, Vervielfältigung und Verbreitung des Quellcodes erforderlichen Rechte im vereinbarten Umfang. Die Zweckübertragungsregel des § 31 Abs. 5 UrhG ist zu beachten.</p>
<p>Das Urheberrecht (Urheberpersönlichkeitsrecht) gemäß §§ 12-14 UrhG verbleibt beim Verkäufer und ist nicht übertragbar.</p>
<p><strong>Vorbestehende Rechte Dritter:</strong> Der Verkäufer gewährleistet, dass der Quellcode keine Rechte Dritter verletzt und frei von Rechten Dritter ist, soweit in diesem Vertrag nicht anders angegeben.</p>
</div>

<h2>§ 3 Lieferung und Übergabe</h2>
<div class="clause">
<p>Lieferungsart: <strong>{{delivery_type_label}}</strong></p>
<p>Die Übergabe des Quellcodes erfolgt innerhalb von 14 Tagen nach vollständiger Kaufpreiszahlung.</p>
<p>Der Quellcode wird in einem gängigen, lauffähigen Zustand übergeben. Zur Übergabe gehören:</p>
<ul>
<li>Vollständiger Quellcode inklusive aller Abhängigkeiten</li>
<li>Build-Konfiguration und Deployment-Skripte</li>
{{#includes_documentation}}<li>Technische Dokumentation (API-Docs, Architektur-Übersicht, Installationsanleitung)</li>{{/includes_documentation}}
<li>Liste der verwendeten Open-Source-Komponenten mit Lizenzen</li>
</ul>
</div>

{{#includes_deployment_support}}<h2>§ 4 Deployment-Unterstützung</h2>
<div class="clause">
<p>Der Verkäufer unterstützt den Käufer bei der Inbetriebnahme der Software für einen Zeitraum von 5 Werktagen nach Übergabe. Dies umfasst:</p>
<ul>
<li>Unterstützung bei der Einrichtung der Entwicklungsumgebung</li>
<li>Erläuterung der Architektur und Code-Struktur</li>
<li>Unterstützung bei der erstmaligen Produktivsetzung</li>
</ul>
</div>{{/includes_deployment_support}}

{{#open_source_included}}<h2>Open-Source-Komponenten</h2>
<div class="clause">
<p>Der Quellcode enthält Open-Source-Komponenten. Der Verkäufer stellt eine vollständige Liste der verwendeten Open-Source-Bibliotheken mit den jeweiligen Lizenzbedingungen (z.B. MIT, Apache 2.0, BSD, GPL, LGPL) als Anlage bereit.</p>
<p>Der Verkäufer gewährleistet, dass die Verwendung der Open-Source-Komponenten nicht zu weitergehenden Lizenzpflichten für den proprietären Code führt (insbesondere kein Copyleft-Effekt auf den proprietären Code), es sei denn, dies ist ausdrücklich in der Anlage aufgeführt.</p>
<p>Der Käufer ist verpflichtet, die Lizenzbedingungen der enthaltenen Open-Source-Komponenten einzuhalten.</p>
</div>{{/open_source_included}}

<h2>Kaufpreis und Zahlung</h2>
<div class="clause">
<p>Der Kaufpreis beträgt <strong>{{total_value}} {{currency}}</strong> und ist {{payment_schedule_label}} zu entrichten.</p>
<p><strong>Zahlungsverzug:</strong> Im Falle des Zahlungsverzugs fallen Verzugszinsen in Höhe von 9 Prozentpunkten über dem Basiszinssatz an (§ 288 Abs. 2 BGB). Zusätzlich wird eine Mahnpauschale von 40 EUR (§ 288 Abs. 5 BGB) erhoben.</p>
<p>Die Übertragung der Rechte steht unter dem Vorbehalt der vollständigen Kaufpreiszahlung (Eigentumsvorbehalt analog § 449 BGB).</p>
</div>

<h2>Gewährleistung</h2>
<div class="clause">
<p>Der Verkäufer gewährleistet, dass der Quellcode zum Zeitpunkt der Übergabe kompilierbar ist und im Wesentlichen der vereinbarten Beschaffenheit entspricht.</p>
<p>Die Gewährleistungsfrist beträgt {{warranty_months}} Monate ab Übergabe.</p>
<p>Die Gewährleistung umfasst die Nachbesserung. Schlägt die Nachbesserung nach zwei Versuchen fehl, kann der Käufer Minderung oder Rücktritt verlangen.</p>
<p><strong>Ausschluss:</strong> Die Gewährleistung erstreckt sich nicht auf Mängel, die durch Änderungen des Käufers am Quellcode verursacht werden.</p>
</div>

<h2>Haftung und Freistellung</h2>
<div class="clause">
<p>Der Verkäufer haftet unbeschränkt für Vorsatz und grobe Fahrlässigkeit sowie für Schäden an Leben, Körper oder Gesundheit.</p>
<p>Bei leichter Fahrlässigkeit haftet der Verkäufer nur bei Verletzung wesentlicher Vertragspflichten (Kardinalpflichten). In diesem Fall ist die Haftung auf den vorhersehbaren, vertragstypischen Schaden begrenzt.</p>
<p>Die Haftung ist auf die Höhe des Kaufpreises, mindestens jedoch 5.000 EUR, beschränkt. Dies gilt nicht für die in Absatz 1 genannten Fälle.</p>
<p><strong>IP-Freistellung:</strong> Der Verkäufer stellt den Käufer von sämtlichen Ansprüchen Dritter frei, die sich aus einer Verletzung von Schutzrechten durch den Quellcode ergeben, soweit der Käufer den Quellcode vertragsgemäß nutzt.</p>
</div>

<h2>Höhere Gewalt</h2>
<div class="clause">
<p>Keine Partei haftet für die Nichterfüllung oder verzögerte Erfüllung ihrer Pflichten, soweit dies auf Umstände zurückzuführen ist, die außerhalb ihrer zumutbaren Kontrolle liegen (höhere Gewalt). Dazu zählen insbesondere Naturkatastrophen, Krieg, Pandemien, Streiks sowie behördliche Anordnungen.</p>
<p>Die betroffene Partei hat die andere Partei unverzüglich über den Eintritt und die voraussichtliche Dauer der höheren Gewalt zu informieren.</p>
</div>

<h2>Schlussbestimmungen</h2>
<div class="clause">
<p><strong>B2B-Klausel:</strong> Dieser Vertrag richtet sich ausschließlich an Unternehmer im Sinne von § 14 BGB. Der Käufer bestätigt, dass er diesen Vertrag im Rahmen seiner gewerblichen oder selbständigen beruflichen Tätigkeit abschließt.</p>
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}. Das Übereinkommen der Vereinten Nationen über Verträge über den internationalen Warenkauf (CISG) findet keine Anwendung.</p>
<p>Änderungen bedürfen der Schriftform. Dies gilt auch für die Änderung dieser Schriftformklausel.</p>
<p>Sollte eine Bestimmung dieses Vertrages unwirksam sein, so wird die Wirksamkeit der übrigen Bestimmungen hiervon nicht berührt. Die Parteien verpflichten sich, die unwirksame Bestimmung durch eine wirksame zu ersetzen, die dem wirtschaftlichen Zweck möglichst nahekommt.</p>
</div>
');

-- 11. NEW Source Code Purchase Agreement (EN) — tpl-scp-en
INSERT INTO contract_templates (id, contract_type, language, name, content_html) VALUES ('tpl-scp-en', 'source_code_purchase', 'en', 'Source Code Purchase Agreement', '
<h2>1. Subject Matter</h2>
<div class="clause">
<p>The Seller sells and transfers to the Buyer the source code of the software <strong>{{software_name}}</strong> (Version {{software_version}}) including the associated rights in accordance with the provisions of this agreement.</p>
<p>Scope of source code: <strong>{{source_code_scope}}</strong></p>
</div>

<h2>2. Transfer of Rights</h2>
<div class="clause">
<p>Type of rights transfer: <strong>{{ip_transfer_type_label}}</strong></p>
<p>The transfer encompasses all rights necessary for the use, modification, reproduction, and distribution of the source code within the agreed scope. The purpose transfer rule of § 31(5) German Copyright Act applies.</p>
<p>The moral rights (author''s personality rights) pursuant to §§ 12-14 German Copyright Act remain with the Seller and are non-transferable.</p>
<p><strong>Pre-existing Third-Party Rights:</strong> The Seller warrants that the source code does not infringe any third-party rights and is free from third-party encumbrances, unless otherwise stated in this agreement.</p>
</div>

<h2>3. Delivery and Handover</h2>
<div class="clause">
<p>Delivery method: <strong>{{delivery_type_label}}</strong></p>
<p>The source code shall be delivered within 14 days of full payment of the purchase price.</p>
<p>The source code shall be delivered in a common, compilable state. The delivery includes:</p>
<ul>
<li>Complete source code including all dependencies</li>
<li>Build configuration and deployment scripts</li>
{{#includes_documentation}}<li>Technical documentation (API docs, architecture overview, installation guide)</li>{{/includes_documentation}}
<li>List of open-source components used with their licenses</li>
</ul>
</div>

{{#includes_deployment_support}}<h2>4. Deployment Support</h2>
<div class="clause">
<p>The Seller shall support the Buyer in deploying the software for a period of 5 business days after delivery. This includes:</p>
<ul>
<li>Support with setting up the development environment</li>
<li>Explanation of architecture and code structure</li>
<li>Support with initial production deployment</li>
</ul>
</div>{{/includes_deployment_support}}

{{#open_source_included}}<h2>Open Source Components</h2>
<div class="clause">
<p>The source code contains open-source components. The Seller shall provide a complete list of open-source libraries used, including their respective license terms (e.g., MIT, Apache 2.0, BSD, GPL, LGPL), as an appendix.</p>
<p>The Seller warrants that the use of open-source components does not create additional license obligations for the proprietary code (in particular, no copyleft effect on the proprietary code), unless expressly listed in the appendix.</p>
<p>The Buyer is obligated to comply with the license terms of the included open-source components.</p>
</div>{{/open_source_included}}

<h2>Purchase Price and Payment</h2>
<div class="clause">
<p>The purchase price amounts to <strong>{{total_value}} {{currency}}</strong>, payable {{payment_schedule_label}}.</p>
<p><strong>Late Payment:</strong> In case of late payment, default interest of 9 percentage points above the base interest rate applies (§ 288(2) German Civil Code). Additionally, a flat-rate dunning fee of EUR 40 (§ 288(5) German Civil Code) will be charged.</p>
<p>The transfer of rights is subject to full payment of the purchase price (retention of title analogous to § 449 German Civil Code).</p>
</div>

<h2>Warranty</h2>
<div class="clause">
<p>The Seller warrants that the source code is compilable at the time of delivery and substantially conforms to the agreed specifications.</p>
<p>The warranty period is {{warranty_months}} months from delivery.</p>
<p>The warranty covers remediation. If remediation fails after two attempts, the Buyer may demand a price reduction or rescission.</p>
<p><strong>Exclusion:</strong> The warranty does not cover defects caused by modifications made by the Buyer to the source code.</p>
</div>

<h2>Liability and Indemnification</h2>
<div class="clause">
<p>The Seller is liable without limitation for willful misconduct and gross negligence, and for damages to life, body, or health.</p>
<p>In cases of slight negligence, the Seller is liable only for breach of essential contractual obligations (cardinal obligations). In such cases, liability is limited to the foreseeable, contract-typical damage.</p>
<p>Liability is limited to the purchase price, but no less than EUR 5,000. This does not apply to the cases mentioned in paragraph 1.</p>
<p><strong>IP Indemnification:</strong> The Seller shall indemnify and hold harmless the Buyer from all third-party claims arising from infringement of intellectual property rights by the source code, to the extent the Buyer uses the source code in accordance with this agreement.</p>
</div>

<h2>Force Majeure</h2>
<div class="clause">
<p>Neither party shall be liable for non-performance or delayed performance of its obligations to the extent caused by circumstances beyond its reasonable control (force majeure), including natural disasters, war, pandemics, strikes, and governmental orders.</p>
<p>The affected party shall promptly notify the other party of the occurrence and expected duration of the force majeure event.</p>
</div>

<h2>General Provisions</h2>
<div class="clause">
<p><strong>B2B Clause:</strong> This agreement is directed exclusively at entrepreneurs within the meaning of § 14 German Civil Code (BGB). The Buyer confirms that it is entering into this agreement in the course of its commercial or independent professional activity.</p>
<p>This agreement is governed by the laws of {{governing_law_label}}. Jurisdiction is {{jurisdiction}}. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.</p>
<p>Amendments require written form. This also applies to any waiver of this written form requirement.</p>
<p>If any provision of this agreement is or becomes invalid, the validity of the remaining provisions shall not be affected. The parties undertake to replace the invalid provision with a valid provision that most closely achieves the economic purpose of the invalid provision.</p>
</div>
');
