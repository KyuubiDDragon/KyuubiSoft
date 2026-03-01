-- 115_update_contract_templates.sql
-- Legal review: Comprehensive fix of all contract templates
-- Fixes: B2B clause (§14 BGB), liability caps (§309 Nr.7 BGB), Kardinalpflichten,
-- Force Majeure, termination clauses, reverse engineering (§69e UrhG),
-- DSGVO, GeschGehG, HinSchG, §§327a-327u BGB (B2C digital products)

-- 1. Softwarelizenzvertrag (DE) — tpl-license-de
UPDATE contract_templates SET content_html = '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Auftragnehmer räumt dem Auftraggeber eine {{license_type_label}} Lizenz zur Nutzung der Software <strong>{{software_name}}</strong> (Version {{software_version}}) ein.</p>
<p>Die Lizenz umfasst die Nutzung durch maximal <strong>{{max_users}}</strong> Nutzer im Gebiet <strong>{{territory_label}}</strong>.</p>
</div>

<h2>§ 2 Nutzungsrechte und Urheberrecht</h2>
<div class="clause">
<p>Der Auftraggeber erhält das Recht, die Software für eigene geschäftliche Zwecke zu nutzen. Eine Unterlizenzierung oder Weitergabe an Dritte ist ohne schriftliche Zustimmung des Auftragnehmers nicht gestattet. Der Weiterverkauf der Software ist nicht gestattet.</p>
<p><strong>Zweckübertragungsregel (§ 31 Abs. 5 UrhG):</strong> Die Einräumung von Nutzungsrechten erstreckt sich nur auf die in diesem Vertrag ausdrücklich genannten Nutzungsarten. Nicht ausdrücklich eingeräumte Rechte verbleiben beim Auftragnehmer.</p>
<p>{{#source_code_access}}Der Auftraggeber erhält Zugang zum Quellcode der Software. Die Nutzung des Quellcodes ist ausschließlich auf die in diesem Vertrag genannten Zwecke beschränkt. Eine Weitergabe des Quellcodes an Dritte ist nicht gestattet. Der Auftraggeber darf den Quellcode zur Herstellung der Interoperabilität mit eigener Software untersuchen und anpassen, soweit dies nach § 69e UrhG zulässig ist.{{/source_code_access}}{{^source_code_access}}Der Quellcode wird nicht übergeben. Dem Auftraggeber ist es untersagt, die Software zu dekompilieren, zurückzuentwickeln (Reverse Engineering) oder auf sonstige Weise den Quellcode zu ermitteln, es sei denn, dies ist nach § 69e UrhG zur Herstellung der Interoperabilität zwingend erlaubt.{{/source_code_access}}</p>
<p>{{#modification_rights}}Der Auftraggeber ist berechtigt, die Software für eigene Zwecke anzupassen. Die Rechte an den Anpassungen verbleiben beim Auftraggeber, soweit sie eigenständige Schöpfungen darstellen. Die Integration der Software in eigene Produkte des Auftraggebers ist gestattet, eine Weitergabe der integrierten Software an Dritte bedarf der Zustimmung des Auftragnehmers.{{/modification_rights}}{{^modification_rights}}Änderungen an der Software sind nicht gestattet.{{/modification_rights}}</p>
<p>Die Herstellung von Sicherungskopien gemäß § 69d Abs. 2 UrhG bleibt unberührt.</p>
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
</div>

<h2>§ 5 Gewährleistung</h2>
<div class="clause">
<p>Der Auftragnehmer gewährleistet, dass die Software im Wesentlichen der Dokumentation entspricht. Ein Mangel liegt vor, wenn die Software nicht die vereinbarte Beschaffenheit aufweist oder sich nicht für die vertraglich vorausgesetzte Verwendung eignet.</p>
<p>{{#is_b2c}}Die Gewährleistungsfrist beträgt 24 Monate ab Bereitstellung (§ 327j BGB). Für Mängel, die sich innerhalb von 12 Monaten nach Bereitstellung zeigen, wird vermutet, dass sie bereits bei Bereitstellung vorlagen (§ 327k BGB).{{/is_b2c}}{{^is_b2c}}Die Gewährleistungsfrist beträgt 12 Monate ab Lieferung.{{/is_b2c}}</p>
<p>Die Gewährleistung umfasst die Nachbesserung oder Ersatzlieferung nach Wahl des Auftragnehmers. Schlägt die Nachbesserung nach zwei Versuchen fehl, kann der Auftraggeber Minderung oder Rücktritt verlangen.</p>
</div>

<h2>§ 6 Haftung</h2>
<div class="clause">
<p>Der Auftragnehmer haftet unbeschränkt für Vorsatz und grobe Fahrlässigkeit sowie für Schäden an Leben, Körper oder Gesundheit, für Ansprüche aus dem Produkthaftungsgesetz sowie für arglistig verschwiegene Mängel.</p>
<p>Bei leichter Fahrlässigkeit haftet der Auftragnehmer nur bei Verletzung wesentlicher Vertragspflichten (Kardinalpflichten). Wesentliche Vertragspflichten sind solche, deren Erfüllung die ordnungsgemäße Durchführung des Vertrages überhaupt erst ermöglicht und auf deren Einhaltung der Vertragspartner regelmäßig vertrauen darf. In diesem Fall ist die Haftung auf den vorhersehbaren, vertragstypischen Schaden begrenzt.</p>
<p>Die Haftung ist in jedem Fall auf die Höhe der Lizenzgebühr, mindestens jedoch 5.000 EUR, beschränkt. Dies gilt nicht für die in Absatz 1 genannten Fälle.</p>
</div>

<h2>§ 7 Laufzeit und Kündigung</h2>
<div class="clause">
<p>Der Vertrag beginnt am {{start_date}} {{#end_date}}und endet am {{end_date}}{{/end_date}}{{^end_date}}und läuft auf unbestimmte Zeit{{/end_date}}.</p>
<p>Die Kündigungsfrist beträgt {{notice_period_days}} Tage {{#end_date}}zum Ende der jeweiligen Vertragslaufzeit{{/end_date}}{{^end_date}}zum Monatsende{{/end_date}}.</p>
<p>Das Recht zur außerordentlichen Kündigung aus wichtigem Grund (§ 314 BGB) bleibt unberührt.</p>
</div>

{{#include_nda_clause}}<h2>§ 8 Vertraulichkeit</h2>
<div class="clause">
<p>Die Parteien verpflichten sich, alle im Rahmen dieses Vertrages erlangten vertraulichen Informationen der jeweils anderen Partei geheim zu halten und nur für die Zwecke dieses Vertrages zu verwenden. Diese Pflicht gilt auch nach Vertragsende für einen Zeitraum von 3 Jahren fort.</p>
<p>Die Geheimhaltungspflicht gilt nicht für Informationen, die öffentlich bekannt sind, die von Dritten rechtmäßig erlangt wurden oder die aufgrund gesetzlicher Verpflichtung offengelegt werden müssen.</p>
</div>{{/include_nda_clause}}

{{#is_b2c}}<h2>§ 9 Widerrufsbelehrung (Verbraucher)</h2>
<div class="clause">
<p><strong>Widerrufsrecht</strong></p>
<p>Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag des Vertragsschlusses. Um Ihr Widerrufsrecht auszuüben, müssen Sie uns ({{party_a_company}}, {{party_a_address}}, E-Mail: {{party_a_email}}) mittels einer eindeutigen Erklärung (z.B. ein mit der Post versandter Brief oder E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.</p>
<p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p>
<p><strong>Folgen des Widerrufs</strong></p>
<p>Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist. Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben.</p>
<p><strong>Besonderer Hinweis bei digitalen Inhalten (§ 356 Abs. 5 BGB):</strong> Sie stimmen ausdrücklich zu, dass wir mit der Ausführung des Vertrages vor Ablauf der Widerrufsfrist beginnen. Sie haben Kenntnis davon, dass Sie mit Beginn der Ausführung des Vertrages Ihr Widerrufsrecht verlieren.</p>
<p><strong>Muster-Widerrufsformular</strong></p>
<p><em>(Wenn Sie den Vertrag widerrufen wollen, füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</em></p>
<p>An: {{party_a_company}}, {{party_a_address}}, {{party_a_email}}<br/>
Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) geschlossenen Vertrag über die Erbringung der folgenden Dienstleistung / den Kauf der folgenden Ware (*)<br/>
Bestellt am (*) / erhalten am (*): _______________<br/>
Name des/der Verbraucher(s): _______________<br/>
Anschrift des/der Verbraucher(s): _______________<br/>
Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier): _______________<br/>
Datum: _______________<br/>
(*) Unzutreffendes streichen.</p>
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

-- 2. Software License Agreement (EN) — tpl-license-en
UPDATE contract_templates SET content_html = '
<h2>1. Subject Matter</h2>
<div class="clause">
<p>The Licensor grants the Licensee a {{license_type_label}} license to use the software <strong>{{software_name}}</strong> (Version {{software_version}}).</p>
<p>The license covers use by up to <strong>{{max_users}}</strong> users in the territory of <strong>{{territory_label}}</strong>.</p>
</div>

<h2>2. Usage Rights and Copyright</h2>
<div class="clause">
<p>The Licensee is entitled to use the software for its own business purposes. Sublicensing or transfer to third parties requires prior written consent from the Licensor. Resale of the software is not permitted.</p>
<p><strong>Purpose Transfer Rule (§ 31(5) German Copyright Act):</strong> The grant of usage rights extends only to the types of use expressly specified in this agreement. All rights not expressly granted remain with the Licensor.</p>
<p>{{#source_code_access}}The Licensee receives access to the source code. Use of the source code is limited exclusively to the purposes stated in this agreement. Transfer of the source code to third parties is not permitted. The Licensee may examine and adapt the source code to achieve interoperability with its own software, to the extent permitted by § 69e German Copyright Act.{{/source_code_access}}{{^source_code_access}}Source code is not provided. The Licensee shall not decompile, reverse-engineer, or otherwise attempt to derive the source code of the software, except as mandated by § 69e German Copyright Act for the purpose of achieving interoperability.{{/source_code_access}}</p>
<p>{{#modification_rights}}The Licensee may modify the software for its own purposes. Rights to modifications remain with the Licensee to the extent they constitute independent works. Integration of the software into the Licensee''s own products is permitted; transfer of the integrated software to third parties requires the Licensor''s consent.{{/modification_rights}}{{^modification_rights}}Modifications to the software are not permitted.{{/modification_rights}}</p>
<p>The right to create backup copies pursuant to § 69d(2) German Copyright Act remains unaffected.</p>
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
</div>

<h2>5. Warranty</h2>
<div class="clause">
<p>The Licensor warrants that the software substantially conforms to its documentation. A defect exists if the software does not have the agreed quality or is not suitable for the contractually intended use.</p>
<p>{{#is_b2c}}The warranty period is 24 months from provision (§ 327j German Civil Code). For defects appearing within 12 months of provision, it is presumed that they existed at the time of provision (§ 327k German Civil Code).{{/is_b2c}}{{^is_b2c}}The warranty period is 12 months from delivery.{{/is_b2c}}</p>
<p>The warranty covers repair or replacement at the Licensor''s discretion. If repair fails after two attempts, the Licensee may demand a price reduction or rescission.</p>
</div>

<h2>6. Limitation of Liability</h2>
<div class="clause">
<p>The Licensor is liable without limitation for willful misconduct and gross negligence, for damages to life, body, or health, for claims under the German Product Liability Act, and for fraudulently concealed defects.</p>
<p>In cases of slight negligence, the Licensor is liable only for breach of essential contractual obligations (cardinal obligations). Essential contractual obligations are those whose fulfilment is necessary for the proper performance of the contract and on whose compliance the contractual partner may regularly rely. In such cases, liability is limited to the foreseeable, contract-typical damage.</p>
<p>In any case, liability is limited to the amount of the license fee, but no less than EUR 5,000. This does not apply to the cases mentioned in paragraph 1.</p>
</div>

<h2>7. Term and Termination</h2>
<div class="clause">
<p>This agreement commences on {{start_date}} {{#end_date}}and terminates on {{end_date}}{{/end_date}}{{^end_date}}and continues for an indefinite period{{/end_date}}.</p>
<p>The notice period is {{notice_period_days}} days {{#end_date}}before the end of the respective contract period{{/end_date}}{{^end_date}}to the end of the month{{/end_date}}.</p>
<p>The right to extraordinary termination for cause (§ 314 German Civil Code) remains unaffected.</p>
</div>

{{#include_nda_clause}}<h2>8. Confidentiality</h2>
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
<p><strong>Model Withdrawal Form</strong></p>
<p><em>(If you wish to withdraw from the contract, please complete and return this form.)</em></p>
<p>To: {{party_a_company}}, {{party_a_address}}, {{party_a_email}}<br/>
I/we (*) hereby withdraw from the contract concluded by me/us (*) for the provision of the following service / purchase of the following goods (*)<br/>
Ordered on (*) / received on (*): _______________<br/>
Name of consumer(s): _______________<br/>
Address of consumer(s): _______________<br/>
Signature of consumer(s) (only for paper notification): _______________<br/>
Date: _______________<br/>
(*) Delete as appropriate.</p>
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

-- 3. Softwareentwicklungsvertrag (DE) — tpl-dev-de
UPDATE contract_templates SET content_html = '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Auftragnehmer verpflichtet sich zur Entwicklung folgender Software gemäß den in diesem Vertrag und dem Pflichtenheft festgelegten Spezifikationen:</p>
<p><strong>{{project_description}}</strong></p>
</div>

<h2>§ 2 Leistungsumfang und Meilensteine</h2>
<div class="clause">
<p>Die Entwicklung erfolgt in den vereinbarten Meilensteinen. Der Auftragnehmer schuldet ein funktionsfähiges Werk gemäß den vereinbarten Spezifikationen (Werkvertrag gemäß §§ 631 ff. BGB).</p>
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

-- 4. Software Development Agreement (EN) — tpl-dev-en
UPDATE contract_templates SET content_html = '
<h2>1. Subject Matter</h2>
<div class="clause">
<p>The Developer agrees to develop the following software in accordance with the specifications set forth in this agreement:</p>
<p><strong>{{project_description}}</strong></p>
</div>

<h2>2. Scope and Milestones</h2>
<div class="clause">
<p>Development shall proceed according to the agreed milestones. The Developer owes a functional work in accordance with the agreed specifications (work contract pursuant to §§ 631 et seq. German Civil Code).</p>
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

-- 5. SaaS-Vertrag (DE) — tpl-saas-de
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
</ul>
</div>

<h2>§ 3 Verfügbarkeit (SLA)</h2>
<div class="clause">
<p>Der Anbieter garantiert eine Verfügbarkeit von <strong>{{sla_uptime}}%</strong> im Monatsmittel, gemessen außerhalb geplanter Wartungsfenster. Geplante Wartungsarbeiten werden mindestens 48 Stunden im Voraus angekündigt.</p>
<p>Unterschreitet der Anbieter die garantierte Verfügbarkeit in einem Kalendermonat, erhält der Kunde eine anteilige Gutschrift auf die Nutzungsgebühr des betroffenen Monats. Ab einer Verfügbarkeit unter 95% im Monatsmittel ist der Kunde zur außerordentlichen Kündigung berechtigt.</p>
</div>

<h2>§ 4 Gewährleistung</h2>
<div class="clause">
<p>Der Anbieter gewährleistet, dass der Dienst im Wesentlichen der Leistungsbeschreibung entspricht. Ein Mangel liegt vor, wenn der Dienst nicht die vereinbarte Beschaffenheit aufweist oder sich nicht für die vertraglich vorausgesetzte Verwendung eignet.</p>
<p>{{#is_b2c}}Die Gewährleistungsfrist beträgt 24 Monate ab Bereitstellung (§ 327j BGB). Für Mängel, die sich innerhalb von 12 Monaten nach Bereitstellung zeigen, wird vermutet, dass sie bereits bei Bereitstellung vorlagen (§ 327k BGB).{{/is_b2c}}{{^is_b2c}}Die Gewährleistungsfrist beträgt 12 Monate ab Bereitstellung.{{/is_b2c}}</p>
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
<p><strong>Muster-Widerrufsformular</strong></p>
<p><em>(Wenn Sie den Vertrag widerrufen wollen, füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</em></p>
<p>An: {{party_a_company}}, {{party_a_address}}, {{party_a_email}}<br/>
Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) geschlossenen Vertrag über die Erbringung der folgenden Dienstleistung (*)<br/>
Bestellt am (*): _______________<br/>
Name des/der Verbraucher(s): _______________<br/>
Anschrift des/der Verbraucher(s): _______________<br/>
Unterschrift (nur bei Mitteilung auf Papier): _______________<br/>
Datum: _______________<br/>
(*) Unzutreffendes streichen.</p>
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

-- 6. SaaS Agreement (EN) — tpl-saas-en
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
</ul>
</div>

<h2>3. Availability (SLA)</h2>
<div class="clause">
<p>The Provider guarantees an uptime of <strong>{{sla_uptime}}%</strong> on a monthly average, excluding scheduled maintenance windows. Planned maintenance will be announced at least 48 hours in advance.</p>
<p>If the Provider falls below the guaranteed availability in a calendar month, the Customer shall receive a proportional credit on the usage fee for the affected month. If availability falls below 95% in a monthly average, the Customer is entitled to extraordinary termination.</p>
</div>

<h2>4. Warranty</h2>
<div class="clause">
<p>The Provider warrants that the service substantially conforms to the service description. A defect exists if the service does not have the agreed quality or is not suitable for the contractually intended use.</p>
<p>{{#is_b2c}}The warranty period is 24 months from provision (§ 327j German Civil Code). For defects appearing within 12 months of provision, it is presumed that they existed at the time of provision (§ 327k German Civil Code).{{/is_b2c}}{{^is_b2c}}The warranty period is 12 months from provision.{{/is_b2c}}</p>
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
<p><strong>Model Withdrawal Form</strong></p>
<p><em>(If you wish to withdraw from the contract, please complete and return this form.)</em></p>
<p>To: {{party_a_company}}, {{party_a_address}}, {{party_a_email}}<br/>
I/we (*) hereby withdraw from the contract concluded by me/us (*) for the provision of the following service (*)<br/>
Ordered on (*): _______________<br/>
Name of consumer(s): _______________<br/>
Address of consumer(s): _______________<br/>
Signature (only for paper notification): _______________<br/>
Date: _______________<br/>
(*) Delete as appropriate.</p>
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

-- 7. Wartungsvertrag (DE) — tpl-maint-de
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

-- 8. Maintenance and Support Agreement (EN) — tpl-maint-en
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

-- 9. Geheimhaltungsvereinbarung / NDA (DE) — tpl-nda-de
UPDATE contract_templates SET content_html = '
<h2>§ 1 Gegenstand</h2>
<div class="clause">
<p>Die Parteien beabsichtigen, im Rahmen ihrer geschäftlichen Zusammenarbeit vertrauliche Informationen auszutauschen. Diese Vereinbarung regelt den Umgang mit diesen Informationen.</p>
<p>Art der Vereinbarung: <strong>{{nda_type_label}}</strong></p>
</div>

<h2>§ 2 Definition vertraulicher Informationen</h2>
<div class="clause">
<p>Vertrauliche Informationen im Sinne dieser Vereinbarung sind sämtliche Informationen, die als vertraulich gekennzeichnet sind oder deren Vertraulichkeit sich aus der Natur der Information ergibt. Dies umfasst insbesondere:</p>
<p>{{confidential_info_description}}</p>
<p>Darüber hinaus: Geschäftsgeheimnisse im Sinne des Gesetzes zum Schutz von Geschäftsgeheimnissen (GeschGehG), technische Daten, Quellcode, Kundendaten, Geschäftspläne, Finanzdaten und Know-how.</p>
<p>Die Parteien erkennen an, dass die vertraulichen Informationen Geschäftsgeheimnisse im Sinne von § 2 GeschGehG darstellen können und unter den Schutz des GeschGehG (Umsetzung der EU-Richtlinie 2016/943 über den Schutz von Geschäftsgeheimnissen) fallen.</p>
</div>

<h2>§ 3 Pflichten</h2>
<div class="clause">
<p>Die empfangende Partei verpflichtet sich:</p>
<ul>
<li>Vertrauliche Informationen nur für den vereinbarten Zweck zu verwenden</li>
<li>Vertrauliche Informationen nicht an Dritte weiterzugeben</li>
<li>Angemessene Geheimhaltungsmaßnahmen im Sinne von § 2 Abs. 1 lit. b GeschGehG zu treffen</li>
<li>Den Zugang auf Mitarbeiter zu beschränken, die die Informationen benötigen und die einer gleichwertigen Vertraulichkeitspflicht unterliegen</li>
</ul>
</div>

<h2>§ 4 Ausnahmen</h2>
<div class="clause">
<p>Die Geheimhaltungspflicht gilt nicht für Informationen, die:</p>
<ul>
<li>Zum Zeitpunkt der Offenlegung bereits öffentlich bekannt waren</li>
<li>Von der empfangenden Partei nachweislich unabhängig entwickelt wurden</li>
<li>Von einem Dritten rechtmäßig und ohne Vertraulichkeitspflicht erhalten wurden</li>
<li>Aufgrund gesetzlicher Verpflichtung oder behördlicher Anordnung offengelegt werden müssen — in diesem Fall ist die offenlegende Partei verpflichtet, die andere Partei unverzüglich vorab zu informieren, soweit gesetzlich zulässig</li>
<li>Im Rahmen des Hinweisgeberschutzgesetzes (HinSchG) offengelegt werden, soweit die Offenlegung zur Meldung von Verstößen erforderlich ist</li>
</ul>
</div>

<h2>§ 5 Laufzeit</h2>
<div class="clause">
<p>Diese Vereinbarung gilt für einen Zeitraum von <strong>{{duration_years}} Jahren</strong> ab Unterzeichnung. Die Geheimhaltungspflicht besteht auch nach Ablauf der Vereinbarung für alle während der Laufzeit erhaltenen Informationen fort.</p>
</div>

<h2>§ 6 Rückgabe und Vernichtung</h2>
<div class="clause">
<p>Auf Verlangen oder bei Beendigung der Vereinbarung sind sämtliche vertrauliche Informationen einschließlich aller Kopien zurückzugeben oder nachweislich zu vernichten. Die empfangende Partei hat die Vernichtung schriftlich zu bestätigen.</p>
<p>Soweit eine vollständige Löschung technisch nicht möglich ist (z.B. in automatischen Backups oder Archivierungssystemen), bleibt die Geheimhaltungspflicht für diese Daten über die Beendigung dieser Vereinbarung hinaus bestehen.</p>
</div>

<h2>§ 7 Unterlassungsanspruch</h2>
<div class="clause">
<p>Bei drohendem oder tatsächlichem Verstoß gegen diese Vereinbarung ist die geschädigte Partei berechtigt, Unterlassung zu verlangen. Die Parteien erkennen an, dass ein Verstoß gegen diese Vereinbarung einen nicht wiedergutzumachenden Schaden verursachen kann, der durch Geldersatz allein nicht ausreichend kompensiert werden kann. Die geschädigte Partei ist daher berechtigt, einstweiligen Rechtsschutz (einstweilige Verfügung) zu beantragen, und zwar zusätzlich zu allen anderen Rechtsbehelfen.</p>
</div>

{{#penalty_amount}}<h2>§ 8 Vertragsstrafe</h2>
<div class="clause">
<p>Bei Verstoß gegen diese Vereinbarung ist eine Vertragsstrafe in Höhe von <strong>{{penalty_amount}} {{currency}}</strong> je Verstoß fällig. Die Geltendmachung weitergehender Schadenersatzansprüche bleibt unberührt. Auf die Vertragsstrafe wird ein etwaiger Schadensersatzanspruch angerechnet.</p>
</div>{{/penalty_amount}}

<h2>Schlussbestimmungen</h2>
<div class="clause">
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}. Das Übereinkommen der Vereinten Nationen über Verträge über den internationalen Warenkauf (CISG) findet keine Anwendung.</p>
<p>Änderungen bedürfen der Schriftform. Dies gilt auch für die Änderung dieser Schriftformklausel.</p>
<p>Sollte eine Bestimmung dieser Vereinbarung unwirksam oder undurchführbar sein, so wird die Wirksamkeit der übrigen Bestimmungen hiervon nicht berührt. Die Parteien verpflichten sich, die unwirksame Bestimmung durch eine wirksame zu ersetzen, die dem wirtschaftlichen Zweck der unwirksamen Bestimmung möglichst nahekommt.</p>
</div>
' WHERE id = 'tpl-nda-de';

-- 10. Non-Disclosure Agreement (EN) — tpl-nda-en
UPDATE contract_templates SET content_html = '
<h2>1. Purpose</h2>
<div class="clause">
<p>The Parties intend to exchange confidential information in the course of their business relationship. This agreement governs the handling of such information.</p>
<p>Type of agreement: <strong>{{nda_type_label}}</strong></p>
</div>

<h2>2. Definition of Confidential Information</h2>
<div class="clause">
<p>Confidential Information means all information that is marked as confidential or whose confidentiality is apparent from its nature. This includes in particular:</p>
<p>{{confidential_info_description}}</p>
<p>Furthermore: trade secrets as defined by the German Trade Secrets Act (GeschGehG), technical data, source code, customer data, business plans, financial data, and know-how.</p>
<p>The Parties acknowledge that the Confidential Information may constitute trade secrets within the meaning of § 2 GeschGehG and falls under the protection of the GeschGehG (implementing EU Directive 2016/943 on the protection of trade secrets).</p>
</div>

<h2>3. Obligations</h2>
<div class="clause">
<p>The receiving party undertakes to:</p>
<ul>
<li>Use Confidential Information only for the agreed purpose</li>
<li>Not disclose Confidential Information to third parties</li>
<li>Take reasonable protective measures within the meaning of § 2(1)(b) GeschGehG</li>
<li>Restrict access to employees who need the information and who are subject to equivalent confidentiality obligations</li>
</ul>
</div>

<h2>4. Exceptions</h2>
<div class="clause">
<p>The confidentiality obligation does not apply to information that:</p>
<ul>
<li>Was publicly known at the time of disclosure</li>
<li>Was demonstrably developed independently by the receiving party</li>
<li>Was lawfully received from a third party without confidentiality obligations</li>
<li>Must be disclosed due to legal obligations or governmental orders — in such case, the disclosing party is obligated to inform the other party in advance without undue delay, to the extent legally permissible</li>
<li>Is disclosed under the German Whistleblower Protection Act (HinSchG) to the extent necessary for reporting violations</li>
</ul>
</div>

<h2>5. Duration</h2>
<div class="clause">
<p>This agreement is valid for a period of <strong>{{duration_years}} years</strong> from signing. The confidentiality obligation survives expiration for all information received during the term.</p>
</div>

<h2>6. Return and Destruction</h2>
<div class="clause">
<p>Upon request or termination, all Confidential Information including copies shall be returned or demonstrably destroyed. The receiving party shall confirm destruction in writing.</p>
<p>To the extent complete deletion is not technically feasible (e.g., in automated backups or archival systems), the confidentiality obligation shall survive the termination of this agreement for such data.</p>
</div>

<h2>7. Injunctive Relief</h2>
<div class="clause">
<p>In the event of a threatened or actual breach of this agreement, the aggrieved party shall be entitled to demand cessation. The Parties acknowledge that a breach of this agreement may cause irreparable harm that cannot be adequately compensated by monetary damages alone. The aggrieved party shall therefore be entitled to seek interim injunctive relief, in addition to all other remedies available.</p>
</div>

{{#penalty_amount}}<h2>8. Contractual Penalty</h2>
<div class="clause">
<p>In case of breach of this agreement, a contractual penalty of <strong>{{penalty_amount}} {{currency}}</strong> per breach is due. The assertion of further damage claims remains unaffected. Any contractual penalty paid shall be credited against damage claims.</p>
</div>{{/penalty_amount}}

<h2>General Provisions</h2>
<div class="clause">
<p>This agreement is governed by the laws of {{governing_law_label}}. Jurisdiction is {{jurisdiction}}. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.</p>
<p>Amendments require written form. This also applies to any waiver of this written form requirement.</p>
<p>If any provision of this agreement is or becomes invalid or unenforceable, the validity of the remaining provisions shall not be affected. The parties undertake to replace the invalid provision with a valid provision that most closely achieves the economic purpose of the invalid provision.</p>
</div>
' WHERE id = 'tpl-nda-en';
