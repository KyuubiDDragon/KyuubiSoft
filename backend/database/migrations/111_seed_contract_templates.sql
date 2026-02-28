-- Seed default contract templates (DE + EN for all 5 types)
-- These templates use {{placeholder}} syntax for variable substitution
-- Conditional sections use {{#flag}}...{{/flag}} and {{^flag}}...{{/flag}}
-- Legal review: BGB, UrhG, DSGVO, GeschGehG, CISG, EU Fernabsatzrecht

-- 1. Softwarelizenzvertrag (DE)
INSERT INTO contract_templates (id, user_id, name, contract_type, language, content_html, variables, is_default) VALUES
('tpl-license-de', 'system', 'Softwarelizenzvertrag', 'license', 'de', '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Auftragnehmer raeumt dem Auftraggeber eine {{license_type_label}} Lizenz zur Nutzung der Software <strong>{{software_name}}</strong> (Version {{software_version}}) ein.</p>
<p>Die Lizenz umfasst die Nutzung durch maximal <strong>{{max_users}}</strong> Nutzer im Gebiet <strong>{{territory_label}}</strong>.</p>
</div>

<h2>§ 2 Nutzungsrechte und Urheberrecht</h2>
<div class="clause">
<p>Der Auftraggeber erhaelt das Recht, die Software fuer eigene geschaeftliche Zwecke zu nutzen. Eine Unterlizenzierung oder Weitergabe an Dritte ist ohne schriftliche Zustimmung des Auftragnehmers nicht gestattet.</p>
<p><strong>Zweckuebertragungsregel (§ 31 Abs. 5 UrhG):</strong> Die Einraeumung von Nutzungsrechten erstreckt sich nur auf die in diesem Vertrag ausdruecklich genannten Nutzungsarten. Nicht ausdruecklich eingeraeumte Rechte verbleiben beim Auftragnehmer.</p>
<p>{{#source_code_access}}Der Auftraggeber erhaelt Zugang zum Quellcode der Software. Die Nutzung des Quellcodes ist auf die in diesem Vertrag genannten Zwecke beschraenkt.{{/source_code_access}}{{^source_code_access}}Der Quellcode wird nicht uebergeben.{{/source_code_access}}</p>
<p>{{#modification_rights}}Der Auftraggeber ist berechtigt, die Software fuer eigene Zwecke anzupassen. Weitergehende Bearbeitungsrechte bestehen nicht.{{/modification_rights}}{{^modification_rights}}Aenderungen an der Software sind nicht gestattet.{{/modification_rights}}</p>
<p>Dem Auftraggeber ist es untersagt, die Software zu dekompilieren, zurueckzuentwickeln (Reverse Engineering) oder auf sonstige Weise den Quellcode zu ermitteln, es sei denn, dies ist nach § 69e UrhG zwingend erlaubt. Die Herstellung von Sicherungskopien gemaess § 69d Abs. 2 UrhG bleibt unberuehrt.</p>
</div>

<h2>§ 3 Updates und Support</h2>
<div class="clause">
<p>{{#updates_included}}Updates sind fuer einen Zeitraum von {{updates_duration_months}} Monaten ab Vertragsschluss im Lizenzpreis enthalten. Dies umfasst Fehlerbehebungen und funktionale Verbesserungen.{{/updates_included}}{{^updates_included}}Updates sind nicht im Lizenzpreis enthalten und koennen separat erworben werden.{{/updates_included}}</p>
<p>Support-Level: <strong>{{support_level_label}}</strong></p>
</div>

<h2>§ 4 Verguetung</h2>
<div class="clause">
<p>Die Lizenzgebuehr betraegt <strong>{{total_value}} {{currency}}</strong> und ist {{payment_schedule_label}} zu entrichten.</p>
</div>

<h2>§ 5 Gewaehrleistung</h2>
<div class="clause">
<p>Der Auftragnehmer gewaehrleistet, dass die Software im Wesentlichen der Dokumentation entspricht.</p>
<p>{{#is_b2c}}Die Gewaehrleistungsfrist betraegt 24 Monate ab Lieferung (§ 438 BGB).{{/is_b2c}}{{^is_b2c}}Die Gewaehrleistungsfrist betraegt 12 Monate ab Lieferung.{{/is_b2c}}</p>
<p>Die Gewaehrleistung umfasst die Nachbesserung oder Ersatzlieferung nach Wahl des Auftragnehmers. Schlaegt die Nachbesserung nach zwei Versuchen fehl, kann der Auftraggeber Minderung oder Ruecktritt verlangen.</p>
</div>

<h2>§ 6 Haftung</h2>
<div class="clause">
<p>Die Haftung des Auftragnehmers ist auf Vorsatz und grobe Fahrlaessigkeit beschraenkt. Bei leichter Fahrlaessigkeit haftet der Auftragnehmer nur bei Verletzung wesentlicher Vertragspflichten (Kardinalpflichten), begrenzt auf den vorhersehbaren, vertragstypischen Schaden. Die Haftung ist in jedem Fall auf die Hoehe der Lizenzgebuehr beschraenkt.</p>
<p>Die vorstehenden Haftungsbeschraenkungen gelten nicht fuer Schaeden an Leben, Koerper oder Gesundheit, fuer Ansprueche aus dem Produkthaftungsgesetz sowie fuer arglistig verschwiegene Maengel.</p>
</div>

<h2>§ 7 Laufzeit und Kuendigung</h2>
<div class="clause">
<p>Der Vertrag beginnt am {{start_date}} {{#end_date}}und endet am {{end_date}}{{/end_date}}{{^end_date}}und laeuft auf unbestimmte Zeit{{/end_date}}.</p>
<p>Die Kuendigungsfrist betraegt {{notice_period_days}} Tage zum Ende der jeweiligen Vertragslaufzeit.</p>
<p>Das Recht zur ausserordentlichen Kuendigung aus wichtigem Grund bleibt unberuehrt.</p>
</div>

{{#is_b2c}}<h2>§ 8 Widerrufsbelehrung (Verbraucher)</h2>
<div class="clause">
<p><strong>Widerrufsrecht</strong></p>
<p>Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gruenden diesen Vertrag zu widerrufen. Die Widerrufsfrist betraegt vierzehn Tage ab dem Tag des Vertragsschlusses. Um Ihr Widerrufsrecht auszuueben, muessen Sie uns ({{party_a_company}}, {{party_a_address}}, E-Mail: {{party_a_email}}) mittels einer eindeutigen Erklaerung (z.B. ein mit der Post versandter Brief oder E-Mail) ueber Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.</p>
<p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung ueber die Ausuebung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p>
<p><strong>Folgen des Widerrufs</strong></p>
<p>Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, unverzueglich und spaetestens binnen vierzehn Tagen ab dem Tag zurueckzuzahlen, an dem die Mitteilung ueber Ihren Widerruf dieses Vertrags bei uns eingegangen ist. Fuer diese Rueckzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der urspruenglichen Transaktion eingesetzt haben.</p>
<p><strong>Besonderer Hinweis bei digitalen Inhalten (§ 356 Abs. 5 BGB):</strong> Sie stimmen ausdruecklich zu, dass wir mit der Ausfuehrung des Vertrages vor Ablauf der Widerrufsfrist beginnen. Sie haben Kenntnis davon, dass Sie mit Beginn der Ausfuehrung des Vertrages Ihr Widerrufsrecht verlieren.</p>
<p><strong>Muster-Widerrufsformular</strong></p>
<p><em>(Wenn Sie den Vertrag widerrufen wollen, fuellen Sie bitte dieses Formular aus und senden Sie es zurueck.)</em></p>
<p>An: {{party_a_company}}, {{party_a_address}}, {{party_a_email}}<br/>
Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) geschlossenen Vertrag ueber die Erbringung der folgenden Dienstleistung / den Kauf der folgenden Ware (*)<br/>
Bestellt am (*) / erhalten am (*): _______________<br/>
Name des/der Verbraucher(s): _______________<br/>
Anschrift des/der Verbraucher(s): _______________<br/>
Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier): _______________<br/>
Datum: _______________<br/>
(*) Unzutreffendes streichen.</p>
</div>{{/is_b2c}}

<h2>§ {{#is_b2c}}9{{/is_b2c}}{{^is_b2c}}8{{/is_b2c}} Schlussbestimmungen</h2>
<div class="clause">
<p>Es gilt das Recht der {{governing_law_label}}. Das Uebereinkommen der Vereinten Nationen ueber Vertraege ueber den internationalen Warenkauf (CISG) findet keine Anwendung.</p>
<p>{{^is_b2c}}Gerichtsstand ist {{jurisdiction}}.{{/is_b2c}}{{#is_b2c}}Fuer Verbraucher gilt der gesetzliche Gerichtsstand.{{/is_b2c}}</p>
<p>Aenderungen und Ergaenzungen dieses Vertrages beduerfen der Schriftform. Dies gilt auch fuer die Aenderung dieser Schriftformklausel.</p>
<p>Sollte eine Bestimmung dieses Vertrages unwirksam oder undurchfuehrbar sein, so wird die Wirksamkeit der uebrigen Bestimmungen hiervon nicht beruehrt. Die Parteien verpflichten sich, die unwirksame Bestimmung durch eine wirksame zu ersetzen, die dem wirtschaftlichen Zweck der unwirksamen Bestimmung moeglichst nahekommt.</p>
</div>
', NULL, 1);

-- 2. Software License Agreement (EN)
INSERT INTO contract_templates (id, user_id, name, contract_type, language, content_html, variables, is_default) VALUES
('tpl-license-en', 'system', 'Software License Agreement', 'license', 'en', '
<h2>1. Subject Matter</h2>
<div class="clause">
<p>The Licensor grants the Licensee a {{license_type_label}} license to use the software <strong>{{software_name}}</strong> (Version {{software_version}}).</p>
<p>The license covers use by up to <strong>{{max_users}}</strong> users in the territory of <strong>{{territory_label}}</strong>.</p>
</div>

<h2>2. Usage Rights and Copyright</h2>
<div class="clause">
<p>The Licensee is entitled to use the software for its own business purposes. Sublicensing or transfer to third parties requires prior written consent from the Licensor.</p>
<p><strong>Purpose Transfer Rule (§ 31(5) German Copyright Act):</strong> The grant of usage rights extends only to the types of use expressly specified in this agreement. All rights not expressly granted remain with the Licensor.</p>
<p>{{#source_code_access}}The Licensee receives access to the source code. Use of the source code is limited to the purposes stated in this agreement.{{/source_code_access}}{{^source_code_access}}Source code is not provided.{{/source_code_access}}</p>
<p>{{#modification_rights}}The Licensee may modify the software for its own purposes. No further modification rights are granted.{{/modification_rights}}{{^modification_rights}}Modifications to the software are not permitted.{{/modification_rights}}</p>
<p>The Licensee shall not decompile, reverse-engineer, or otherwise attempt to derive the source code of the software, except as mandated by applicable law (§ 69e German Copyright Act). The right to create backup copies pursuant to § 69d(2) German Copyright Act remains unaffected.</p>
</div>

<h2>3. Updates and Support</h2>
<div class="clause">
<p>{{#updates_included}}Updates are included for a period of {{updates_duration_months}} months from contract execution. This includes bug fixes and functional improvements.{{/updates_included}}{{^updates_included}}Updates are not included in the license fee and may be purchased separately.{{/updates_included}}</p>
<p>Support Level: <strong>{{support_level_label}}</strong></p>
</div>

<h2>4. Fees</h2>
<div class="clause">
<p>The license fee amounts to <strong>{{total_value}} {{currency}}</strong>, payable {{payment_schedule_label}}.</p>
</div>

<h2>5. Warranty</h2>
<div class="clause">
<p>The Licensor warrants that the software substantially conforms to its documentation.</p>
<p>{{#is_b2c}}The warranty period is 24 months from delivery (§ 438 German Civil Code).{{/is_b2c}}{{^is_b2c}}The warranty period is 12 months from delivery.{{/is_b2c}}</p>
<p>The warranty covers repair or replacement at the Licensor''s discretion. If repair fails after two attempts, the Licensee may demand a price reduction or rescission.</p>
</div>

<h2>6. Limitation of Liability</h2>
<div class="clause">
<p>The Licensor''s liability is limited to willful misconduct and gross negligence. In cases of slight negligence, liability is limited to breach of essential contractual obligations (cardinal obligations) and the foreseeable, contract-typical damage. In any case, liability is limited to the amount of the license fee.</p>
<p>The above limitations of liability do not apply to damages to life, body, or health, to claims under the German Product Liability Act, or to fraudulently concealed defects.</p>
</div>

<h2>7. Term and Termination</h2>
<div class="clause">
<p>This agreement commences on {{start_date}} {{#end_date}}and terminates on {{end_date}}{{/end_date}}{{^end_date}}and continues for an indefinite period{{/end_date}}.</p>
<p>The notice period is {{notice_period_days}} days before the end of the respective contract period.</p>
<p>The right to extraordinary termination for cause remains unaffected.</p>
</div>

{{#is_b2c}}<h2>8. Right of Withdrawal (Consumers)</h2>
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

<h2>{{#is_b2c}}9{{/is_b2c}}{{^is_b2c}}8{{/is_b2c}}. General Provisions</h2>
<div class="clause">
<p>This agreement is governed by the laws of {{governing_law_label}}. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.</p>
<p>{{^is_b2c}}The place of jurisdiction is {{jurisdiction}}.{{/is_b2c}}{{#is_b2c}}For consumers, the statutory place of jurisdiction applies.{{/is_b2c}}</p>
<p>Amendments to this agreement must be made in writing. This also applies to any waiver of this written form requirement.</p>
<p>If any provision of this agreement is or becomes invalid or unenforceable, the validity of the remaining provisions shall not be affected. The parties undertake to replace the invalid provision with a valid provision that most closely achieves the economic purpose of the invalid provision.</p>
</div>
', NULL, 1);

-- 3. Softwareentwicklungsvertrag (DE)
INSERT INTO contract_templates (id, user_id, name, contract_type, language, content_html, variables, is_default) VALUES
('tpl-dev-de', 'system', 'Softwareentwicklungsvertrag', 'development', 'de', '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Auftragnehmer verpflichtet sich zur Entwicklung folgender Software gemaess den in diesem Vertrag und dem Pflichtenheft festgelegten Spezifikationen:</p>
<p><strong>{{project_description}}</strong></p>
</div>

<h2>§ 2 Leistungsumfang und Meilensteine</h2>
<div class="clause">
<p>Die Entwicklung erfolgt in den vereinbarten Meilensteinen. Der Auftragnehmer schuldet ein funktionsfaehiges Werk gemaess den vereinbarten Spezifikationen (Werkvertrag gemaess §§ 631 ff. BGB).</p>
</div>

<h2>§ 3 Mitwirkungspflichten des Auftraggebers</h2>
<div class="clause">
<p>Der Auftraggeber ist verpflichtet, den Auftragnehmer bei der Vertragsdurchfuehrung in angemessenem Umfang zu unterstuetzen. Zu den Mitwirkungspflichten gehoeren insbesondere:</p>
<ul>
<li>Rechtzeitige Bereitstellung aller fuer die Entwicklung erforderlichen Informationen, Daten und Unterlagen</li>
<li>Benennung eines fachlich qualifizierten Ansprechpartners mit Entscheidungsbefugnis</li>
<li>Bereitstellung erforderlicher Testumgebungen und Zugaenge</li>
<li>Rechtzeitige Rueckmeldung zu vorgelegten Zwischenergebnissen (innerhalb von 5 Werktagen)</li>
<li>Mitwirkung bei der Abnahme gemaess § 5 dieses Vertrages</li>
</ul>
<p>Kommt der Auftraggeber seinen Mitwirkungspflichten nicht nach und entstehen hierdurch Verzoegerungen, verlaengern sich die vereinbarten Fristen entsprechend. Mehrkosten, die durch mangelnde Mitwirkung entstehen, traegt der Auftraggeber.</p>
</div>

<h2>§ 4 Aenderungsverfahren (Change Requests)</h2>
<div class="clause">
<p>Aenderungswuensche, die ueber den vereinbarten Leistungsumfang hinausgehen, sind vom Auftraggeber schriftlich als Change Request einzureichen. Der Auftragnehmer wird innerhalb von 5 Werktagen eine Aufwandsschaetzung mit Kosten- und Zeitangabe vorlegen.</p>
<p>Change Requests werden erst nach schriftlicher Freigabe durch den Auftraggeber umgesetzt. Bis zur Freigabe wird die Entwicklung gemaess dem urspruenglichen Leistungsumfang fortgesetzt.</p>
</div>

<h2>§ 5 Abnahme</h2>
<div class="clause">
<p>{{acceptance_procedure}}</p>
<p>Der Auftraggeber hat die Software innerhalb von 14 Tagen nach Uebergabe zu pruefen und abzunehmen oder Maengel schriftlich zu ruegen. Die Abnahme gilt als erfolgt, wenn der Auftraggeber die Software ohne wesentliche Beanstandungen produktiv einsetzt oder sich nicht innerhalb der Prueffrist schriftlich aeussert.</p>
<p>Unwesentliche Maengel berechtigen nicht zur Abnahmeverweigerung.</p>
</div>

<h2>§ 6 Verguetung</h2>
<div class="clause">
<p>Die Verguetung betraegt <strong>{{total_value}} {{currency}}</strong> und wird {{payment_schedule_label}} abgerechnet.</p>
</div>

<h2>§ 7 Urheberrecht und Nutzungsrechte</h2>
<div class="clause">
<p>Das Urheberrecht an der Software verbleibt beim Auftragnehmer.</p>
<p><strong>Zweckuebertragungsregel (§ 31 Abs. 5 UrhG):</strong> Der Auftraggeber erhaelt ein einfaches, nicht uebertragbares Nutzungsrecht fuer eigene geschaeftliche Zwecke. Nicht ausdruecklich eingeraeumte Nutzungsrechte verbleiben beim Auftragnehmer.</p>
<p>Der Quellcode wird nach vollstaendiger Bezahlung an den Auftraggeber uebergeben.</p>
</div>

<h2>§ 8 Open-Source-Compliance</h2>
<div class="clause">
<p>Der Auftragnehmer wird den Auftraggeber ueber die Verwendung von Open-Source-Komponenten in der Software informieren. Eine Liste der verwendeten Open-Source-Bibliotheken mit den jeweiligen Lizenzbedingungen (z.B. MIT, Apache 2.0, GPL, LGPL) wird als Anlage beigefuegt.</p>
<p>Der Auftragnehmer gewaehrleistet, dass die Verwendung von Open-Source-Komponenten nicht zu Lizenzpflichten fuer die uebrige Software fuehrt (insbesondere kein Copyleft-Effekt), es sei denn, dies wurde ausdruecklich vereinbart.</p>
</div>

<h2>§ 9 Gewaehrleistung</h2>
<div class="clause">
<p>Die Gewaehrleistungsfrist betraegt {{warranty_months}} Monate ab Abnahme. Der Auftragnehmer ist zur Nachbesserung verpflichtet. Bei Fehlschlagen der Nachbesserung (nach zwei Versuchen) kann der Auftraggeber Minderung oder Ruecktritt verlangen.</p>
</div>

<h2>§ 10 Geheimhaltung</h2>
<div class="clause">
<p>Beide Parteien verpflichten sich, vertrauliche Informationen der jeweils anderen Partei nicht an Dritte weiterzugeben. Diese Pflicht besteht auch nach Vertragsende fort.</p>
</div>

<h2>§ 11 Haftung</h2>
<div class="clause">
<p>Die Haftung des Auftragnehmers ist auf die Hoehe der vereinbarten Verguetung beschraenkt. Dies gilt nicht fuer Vorsatz und grobe Fahrlaessigkeit sowie fuer Schaeden an Leben, Koerper oder Gesundheit.</p>
</div>

<h2>§ 12 Schlussbestimmungen</h2>
<div class="clause">
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}. Das Uebereinkommen der Vereinten Nationen ueber Vertraege ueber den internationalen Warenkauf (CISG) findet keine Anwendung.</p>
<p>Aenderungen beduerfen der Schriftform. Dies gilt auch fuer die Aenderung dieser Schriftformklausel.</p>
<p>Sollte eine Bestimmung dieses Vertrages unwirksam sein, so wird die Wirksamkeit der uebrigen Bestimmungen hiervon nicht beruehrt. Die Parteien verpflichten sich, die unwirksame Bestimmung durch eine wirksame zu ersetzen, die dem wirtschaftlichen Zweck moeglichst nahekommt.</p>
</div>
', NULL, 1);

-- 4. Software Development Agreement (EN)
INSERT INTO contract_templates (id, user_id, name, contract_type, language, content_html, variables, is_default) VALUES
('tpl-dev-en', 'system', 'Software Development Agreement', 'development', 'en', '
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
<p>Copyright in the software remains with the Developer.</p>
<p><strong>Purpose Transfer Rule (§ 31(5) German Copyright Act):</strong> The Client receives a non-exclusive, non-transferable license to use the software for its own business purposes. Usage rights not expressly granted remain with the Developer.</p>
<p>Source code shall be transferred to the Client upon full payment.</p>
</div>

<h2>8. Open Source Compliance</h2>
<div class="clause">
<p>The Developer shall inform the Client about the use of open-source components in the software. A list of open-source libraries used, including their respective license terms (e.g., MIT, Apache 2.0, GPL, LGPL), shall be provided as an appendix.</p>
<p>The Developer warrants that the use of open-source components does not create license obligations for the remaining software (in particular, no copyleft effect), unless expressly agreed otherwise.</p>
</div>

<h2>9. Warranty</h2>
<div class="clause">
<p>The warranty period is {{warranty_months}} months from acceptance. The Developer is obligated to remedy defects. If remediation fails (after two attempts), the Client may request a reduction or rescission.</p>
</div>

<h2>10. Confidentiality</h2>
<div class="clause">
<p>Both parties agree to keep confidential information of the other party secret. This obligation survives termination of this agreement.</p>
</div>

<h2>11. Limitation of Liability</h2>
<div class="clause">
<p>The Developer''s liability is limited to the agreed compensation. This does not apply to willful misconduct, gross negligence, or damages to life, body, or health.</p>
</div>

<h2>12. General Provisions</h2>
<div class="clause">
<p>This agreement is governed by the laws of {{governing_law_label}}. Jurisdiction is {{jurisdiction}}. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.</p>
<p>Amendments require written form. This also applies to any waiver of this written form requirement.</p>
<p>If any provision of this agreement is or becomes invalid, the validity of the remaining provisions shall not be affected. The parties undertake to replace the invalid provision with a valid provision that most closely achieves the economic purpose of the invalid provision.</p>
</div>
', NULL, 1);

-- 5. SaaS-Vertrag (DE)
INSERT INTO contract_templates (id, user_id, name, contract_type, language, content_html, variables, is_default) VALUES
('tpl-saas-de', 'system', 'SaaS-Vertrag', 'saas', 'de', '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Anbieter stellt dem Kunden folgenden Cloud-basierten Softwaredienst (SaaS) zur Verfuegung:</p>
<p><strong>{{service_description}}</strong></p>
</div>

<h2>§ 2 Leistungsumfang</h2>
<div class="clause">
<p>Der Dienst umfasst die Bereitstellung der Software ueber das Internet mit folgenden Parametern:</p>
<ul>
<li>Maximale Nutzeranzahl: {{max_users}} (0 = unbegrenzt)</li>
<li>Speicherplatz: {{storage_gb}} GB</li>
<li>Datenstandort: {{data_location_label}}</li>
</ul>
</div>

<h2>§ 3 Verfuegbarkeit (SLA)</h2>
<div class="clause">
<p>Der Anbieter garantiert eine Verfuegbarkeit von <strong>{{sla_uptime}}%</strong> im Monatsmittel, gemessen ausserhalb geplanter Wartungsfenster. Geplante Wartungsarbeiten werden mindestens 48 Stunden im Voraus angekuendigt.</p>
</div>

<h2>§ 4 Datenschutz und Auftragsverarbeitung</h2>
<div class="clause">
<p>Die Verarbeitung personenbezogener Daten erfolgt gemaess DSGVO. Ein separater Auftragsverarbeitungsvertrag (AVV) gemaess Art. 28 DSGVO ist Bestandteil dieses Vertrages und enthaelt mindestens folgende Regelungen:</p>
<ul>
<li><strong>Weisungsbindung:</strong> Der Anbieter verarbeitet personenbezogene Daten ausschliesslich auf dokumentierte Weisung des Kunden (Art. 28 Abs. 3 lit. a DSGVO)</li>
<li><strong>Vertraulichkeit:</strong> Zur Verarbeitung befugte Personen sind zur Vertraulichkeit verpflichtet (Art. 28 Abs. 3 lit. b DSGVO)</li>
<li><strong>Technische und organisatorische Massnahmen (TOM):</strong> Der Anbieter trifft angemessene TOM gemaess Art. 32 DSGVO, insbesondere Verschluesselung, Zugangskontrollen, Backup-Verfahren und regelmaessige Sicherheitspruefungen</li>
<li><strong>Subunternehmer:</strong> Die Beauftragung von Subunternehmern bedarf der vorherigen schriftlichen Zustimmung des Kunden. Der Anbieter fuehrt eine aktuelle Liste der eingesetzten Subunternehmer (Art. 28 Abs. 2 DSGVO)</li>
<li><strong>Kontrollrechte:</strong> Der Kunde hat das Recht, die Einhaltung der technischen und organisatorischen Massnahmen zu ueberpruefen (Art. 28 Abs. 3 lit. h DSGVO)</li>
<li><strong>Loeschpflichten:</strong> Nach Vertragsende werden personenbezogene Daten gemaess Art. 28 Abs. 3 lit. g DSGVO geloescht oder zurueckgegeben</li>
</ul>
<p>Daten werden ausschliesslich in {{data_location_label}} gespeichert.</p>
</div>

<h2>§ 5 Verguetung</h2>
<div class="clause">
<p>Die Nutzungsgebuehr betraegt <strong>{{price_per_period}} {{currency}}</strong> pro {{subscription_model_label}} und ist im Voraus faellig.</p>
</div>

<h2>§ 6 Laufzeit und Kuendigung</h2>
<div class="clause">
<p>Der Vertrag beginnt am {{start_date}} und laeuft {{subscription_model_label}}. Die Kuendigungsfrist betraegt {{notice_period_days}} Tage zum Ende der jeweiligen Abrechnungsperiode.</p>
<p>{{#auto_renewal}}Der Vertrag verlaengert sich automatisch um jeweils einen weiteren Abrechnungszeitraum.{{/auto_renewal}}</p>
<p>Das Recht zur ausserordentlichen Kuendigung aus wichtigem Grund bleibt unberuehrt.</p>
</div>

<h2>§ 7 Datenexport und Vertragsende</h2>
<div class="clause">
<p>Bei Vertragsende stellt der Anbieter dem Kunden seine Daten in einem gaengigen, maschinenlesbaren Format (CSV, JSON oder XML) fuer den Export zur Verfuegung. Der Exportzeitraum betraegt 30 Tage nach Vertragsende. Nach Ablauf dieses Zeitraums werden die Daten unwiderruflich geloescht.</p>
</div>

<h2>§ 8 Haftung</h2>
<div class="clause">
<p>Die Haftung des Anbieters ist auf die in den letzten 12 Monaten gezahlten Nutzungsgebuehren beschraenkt. Dies gilt nicht fuer Vorsatz und grobe Fahrlaessigkeit sowie fuer Schaeden an Leben, Koerper oder Gesundheit.</p>
</div>

<h2>§ 9 Hoehere Gewalt (Force Majeure)</h2>
<div class="clause">
<p>Keine Partei haftet fuer die Nichterfuellung oder verzoegerte Erfuellung ihrer Pflichten, soweit dies auf Umstaende zurueckzufuehren ist, die ausserhalb ihrer zumutbaren Kontrolle liegen (hoehere Gewalt). Dazu zaehlen insbesondere Naturkatastrophen, Krieg, Terrorismus, Pandemien, Streiks, behoerdliche Anordnungen sowie Ausfall wesentlicher Infrastruktur (Strom, Internet, Rechenzentren).</p>
<p>Die betroffene Partei hat die andere Partei unverzueglich ueber den Eintritt und die voraussichtliche Dauer der hoeheren Gewalt zu informieren. Dauert der Zustand der hoeheren Gewalt laenger als 30 Tage an, ist jede Partei berechtigt, den Vertrag ausserordentlich zu kuendigen.</p>
</div>

{{#is_b2c}}<h2>§ 10 Widerrufsbelehrung (Verbraucher)</h2>
<div class="clause">
<p><strong>Widerrufsrecht</strong></p>
<p>Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gruenden diesen Vertrag zu widerrufen. Die Widerrufsfrist betraegt vierzehn Tage ab dem Tag des Vertragsschlusses. Um Ihr Widerrufsrecht auszuueben, muessen Sie uns ({{party_a_company}}, {{party_a_address}}, E-Mail: {{party_a_email}}) mittels einer eindeutigen Erklaerung (z.B. ein mit der Post versandter Brief oder E-Mail) ueber Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.</p>
<p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung ueber die Ausuebung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p>
<p><strong>Folgen des Widerrufs</strong></p>
<p>Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, unverzueglich und spaetestens binnen vierzehn Tagen ab dem Tag zurueckzuzahlen, an dem die Mitteilung ueber Ihren Widerruf dieses Vertrags bei uns eingegangen ist.</p>
<p><strong>Besonderer Hinweis bei digitalen Inhalten (§ 356 Abs. 5 BGB):</strong> Sie stimmen ausdruecklich zu, dass wir mit der Ausfuehrung des Vertrages vor Ablauf der Widerrufsfrist beginnen. Sie haben Kenntnis davon, dass Sie mit Beginn der Ausfuehrung des Vertrages Ihr Widerrufsrecht verlieren.</p>
<p><strong>Muster-Widerrufsformular</strong></p>
<p><em>(Wenn Sie den Vertrag widerrufen wollen, fuellen Sie bitte dieses Formular aus und senden Sie es zurueck.)</em></p>
<p>An: {{party_a_company}}, {{party_a_address}}, {{party_a_email}}<br/>
Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) geschlossenen Vertrag ueber die Erbringung der folgenden Dienstleistung (*)<br/>
Bestellt am (*): _______________<br/>
Name des/der Verbraucher(s): _______________<br/>
Anschrift des/der Verbraucher(s): _______________<br/>
Unterschrift (nur bei Mitteilung auf Papier): _______________<br/>
Datum: _______________<br/>
(*) Unzutreffendes streichen.</p>
</div>{{/is_b2c}}

<h2>§ {{#is_b2c}}11{{/is_b2c}}{{^is_b2c}}10{{/is_b2c}} Schlussbestimmungen</h2>
<div class="clause">
<p>Es gilt das Recht der {{governing_law_label}}. Das Uebereinkommen der Vereinten Nationen ueber Vertraege ueber den internationalen Warenkauf (CISG) findet keine Anwendung.</p>
<p>{{^is_b2c}}Gerichtsstand ist {{jurisdiction}}.{{/is_b2c}}{{#is_b2c}}Fuer Verbraucher gilt der gesetzliche Gerichtsstand.{{/is_b2c}}</p>
<p>Sollte eine Bestimmung dieses Vertrages unwirksam sein, so wird die Wirksamkeit der uebrigen Bestimmungen hiervon nicht beruehrt. Die Parteien verpflichten sich, die unwirksame Bestimmung durch eine wirksame zu ersetzen, die dem wirtschaftlichen Zweck moeglichst nahekommt.</p>
</div>
', NULL, 1);

-- 6. SaaS Agreement (EN)
INSERT INTO contract_templates (id, user_id, name, contract_type, language, content_html, variables, is_default) VALUES
('tpl-saas-en', 'system', 'SaaS Agreement', 'saas', 'en', '
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
</div>

<h2>4. Data Protection and Data Processing</h2>
<div class="clause">
<p>Processing of personal data is carried out in accordance with GDPR. A separate Data Processing Agreement (DPA) pursuant to Art. 28 GDPR forms part of this agreement and contains at minimum the following provisions:</p>
<ul>
<li><strong>Instruction Binding:</strong> The Provider processes personal data exclusively based on documented instructions from the Customer (Art. 28(3)(a) GDPR)</li>
<li><strong>Confidentiality:</strong> Persons authorized to process data are bound by confidentiality (Art. 28(3)(b) GDPR)</li>
<li><strong>Technical and Organizational Measures (TOM):</strong> The Provider implements appropriate TOM pursuant to Art. 32 GDPR, including encryption, access controls, backup procedures, and regular security audits</li>
<li><strong>Sub-processors:</strong> Engagement of sub-processors requires prior written consent from the Customer. The Provider maintains a current list of sub-processors (Art. 28(2) GDPR)</li>
<li><strong>Audit Rights:</strong> The Customer has the right to verify compliance with technical and organizational measures (Art. 28(3)(h) GDPR)</li>
<li><strong>Deletion Obligations:</strong> After contract termination, personal data shall be deleted or returned pursuant to Art. 28(3)(g) GDPR</li>
</ul>
<p>Data is stored exclusively in {{data_location_label}}.</p>
</div>

<h2>5. Fees</h2>
<div class="clause">
<p>The usage fee amounts to <strong>{{price_per_period}} {{currency}}</strong> per {{subscription_model_label}}, payable in advance.</p>
</div>

<h2>6. Term and Termination</h2>
<div class="clause">
<p>This agreement commences on {{start_date}} and runs on a {{subscription_model_label}} basis. The notice period is {{notice_period_days}} days before the end of the respective billing period.</p>
<p>{{#auto_renewal}}The agreement automatically renews for an additional billing period.{{/auto_renewal}}</p>
<p>The right to extraordinary termination for cause remains unaffected.</p>
</div>

<h2>7. Data Export and Termination</h2>
<div class="clause">
<p>Upon termination, the Provider shall make the Customer''s data available for export in a common, machine-readable format (CSV, JSON, or XML). The export period is 30 days after contract termination. After expiry of this period, data will be irrevocably deleted.</p>
</div>

<h2>8. Limitation of Liability</h2>
<div class="clause">
<p>The Provider''s liability is limited to the fees paid in the preceding 12 months. This does not apply to willful misconduct, gross negligence, or damages to life, body, or health.</p>
</div>

<h2>9. Force Majeure</h2>
<div class="clause">
<p>Neither party shall be liable for non-performance or delayed performance of its obligations to the extent caused by circumstances beyond its reasonable control (force majeure). This includes in particular natural disasters, war, terrorism, pandemics, strikes, governmental orders, and failure of essential infrastructure (power, internet, data centers).</p>
<p>The affected party shall promptly notify the other party of the occurrence and expected duration of the force majeure event. If the force majeure event persists for more than 30 days, either party is entitled to terminate the agreement for cause.</p>
</div>

{{#is_b2c}}<h2>10. Right of Withdrawal (Consumers)</h2>
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

<h2>{{#is_b2c}}11{{/is_b2c}}{{^is_b2c}}10{{/is_b2c}}. General Provisions</h2>
<div class="clause">
<p>This agreement is governed by the laws of {{governing_law_label}}. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.</p>
<p>{{^is_b2c}}Jurisdiction is {{jurisdiction}}.{{/is_b2c}}{{#is_b2c}}For consumers, the statutory place of jurisdiction applies.{{/is_b2c}}</p>
<p>If any provision of this agreement is or becomes invalid, the validity of the remaining provisions shall not be affected. The parties undertake to replace the invalid provision with a valid provision that most closely achieves the economic purpose of the invalid provision.</p>
</div>
', NULL, 1);

-- 7. Wartungsvertrag (DE)
INSERT INTO contract_templates (id, user_id, name, contract_type, language, content_html, variables, is_default) VALUES
('tpl-maint-de', 'system', 'Wartungsvertrag', 'maintenance', 'de', '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Auftragnehmer uebernimmt die Wartung und den Support fuer folgende Software:</p>
<p><strong>{{maintained_software}}</strong></p>
</div>

<h2>§ 2 Leistungsumfang</h2>
<div class="clause">
<p>Der Wartungsvertrag umfasst:</p>
<ul>
<li>Support-Kontingent: {{support_hours_monthly}} Stunden pro Monat</li>
<li>Reaktionszeit: {{response_time_label}}</li>
<li>{{#included_patches}}Sicherheits-Patches und Bugfixes{{/included_patches}}</li>
<li>{{#included_minor_updates}}Minor Updates (Funktionserweiterungen){{/included_minor_updates}}</li>
<li>{{#included_major_updates}}Major Updates (neue Hauptversionen){{/included_major_updates}}</li>
</ul>
{{#remote_access_required}}<p>Fuer die Wartung ist ein Remote-Zugang zum System des Auftraggebers erforderlich.</p>{{/remote_access_required}}
</div>

<h2>§ 3 Reaktionszeiten</h2>
<div class="clause">
<p>Der Auftragnehmer reagiert innerhalb der vereinbarten Reaktionszeit von <strong>{{response_time_label}}</strong> auf Supportanfragen waehrend der Geschaeftszeiten (Mo-Fr 9:00-17:00 Uhr).</p>
</div>

<h2>§ 4 Eskalationsverfahren</h2>
<div class="clause">
<p>Bei Stoerungen, die nicht innerhalb der vereinbarten Reaktionszeit behoben werden koennen, gilt folgendes Eskalationsverfahren:</p>
<ul>
<li><strong>Stufe 1 — Technischer Support:</strong> Erstbearbeitung durch den zustaendigen Support-Mitarbeiter innerhalb der vereinbarten Reaktionszeit</li>
<li><strong>Stufe 2 — Projektleitung:</strong> Eskalation an die Projektleitung, wenn nach dem Doppelten der Reaktionszeit keine Loesung vorliegt. Bereitstellung eines Workarounds oder Zeitplans fuer die Behebung</li>
<li><strong>Stufe 3 — Geschaeftsfuehrung:</strong> Eskalation an die Geschaeftsfuehrung beider Parteien, wenn nach 48 Stunden keine Loesung oder akzeptabler Workaround vorliegt</li>
</ul>
</div>

<h2>§ 5 Verguetung</h2>
<div class="clause">
<p>Die Wartungsgebuehr betraegt <strong>{{total_value}} {{currency}}</strong> und wird {{payment_schedule_label}} abgerechnet. Leistungen ueber das vereinbarte Kontingent hinaus werden nach Aufwand abgerechnet.</p>
</div>

<h2>§ 6 Laufzeit und Kuendigung</h2>
<div class="clause">
<p>Der Vertrag beginnt am {{start_date}} und laeuft auf unbestimmte Zeit. Die Kuendigungsfrist betraegt {{notice_period_days}} Tage zum Monatsende.</p>
<p>Das Recht zur ausserordentlichen Kuendigung aus wichtigem Grund bleibt unberuehrt.</p>
</div>

<h2>§ 7 Haftung</h2>
<div class="clause">
<p>Die Haftung ist auf die jaehrliche Wartungsgebuehr beschraenkt. Ausgenommen sind Schaeden durch Vorsatz oder grobe Fahrlaessigkeit sowie Schaeden an Leben, Koerper oder Gesundheit.</p>
</div>

<h2>§ 8 Schlussbestimmungen</h2>
<div class="clause">
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}. Das Uebereinkommen der Vereinten Nationen ueber Vertraege ueber den internationalen Warenkauf (CISG) findet keine Anwendung.</p>
<p>Aenderungen beduerfen der Schriftform.</p>
<p>Sollte eine Bestimmung dieses Vertrages unwirksam sein, so wird die Wirksamkeit der uebrigen Bestimmungen hiervon nicht beruehrt. Die Parteien verpflichten sich, die unwirksame Bestimmung durch eine wirksame zu ersetzen, die dem wirtschaftlichen Zweck moeglichst nahekommt.</p>
</div>
', NULL, 1);

-- 8. Maintenance Agreement (EN)
INSERT INTO contract_templates (id, user_id, name, contract_type, language, content_html, variables, is_default) VALUES
('tpl-maint-en', 'system', 'Maintenance and Support Agreement', 'maintenance', 'en', '
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
<li>{{#included_patches}}Security patches and bug fixes{{/included_patches}}</li>
<li>{{#included_minor_updates}}Minor updates (feature enhancements){{/included_minor_updates}}</li>
<li>{{#included_major_updates}}Major updates (new major versions){{/included_major_updates}}</li>
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

<h2>5. Fees</h2>
<div class="clause">
<p>The maintenance fee amounts to <strong>{{total_value}} {{currency}}</strong>, payable {{payment_schedule_label}}. Services beyond the agreed scope are billed at hourly rates.</p>
</div>

<h2>6. Term and Termination</h2>
<div class="clause">
<p>This agreement commences on {{start_date}} and continues indefinitely. The notice period is {{notice_period_days}} days to the end of the month.</p>
<p>The right to extraordinary termination for cause remains unaffected.</p>
</div>

<h2>7. Limitation of Liability</h2>
<div class="clause">
<p>Liability is limited to the annual maintenance fee. This excludes damages caused by willful misconduct, gross negligence, or damages to life, body, or health.</p>
</div>

<h2>8. General Provisions</h2>
<div class="clause">
<p>This agreement is governed by the laws of {{governing_law_label}}. Jurisdiction is {{jurisdiction}}. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.</p>
<p>Amendments require written form.</p>
<p>If any provision of this agreement is or becomes invalid, the validity of the remaining provisions shall not be affected. The parties undertake to replace the invalid provision with a valid provision that most closely achieves the economic purpose of the invalid provision.</p>
</div>
', NULL, 1);

-- 9. Geheimhaltungsvereinbarung / NDA (DE)
INSERT INTO contract_templates (id, user_id, name, contract_type, language, content_html, variables, is_default) VALUES
('tpl-nda-de', 'system', 'Geheimhaltungsvereinbarung (NDA)', 'nda', 'de', '
<h2>§ 1 Gegenstand</h2>
<div class="clause">
<p>Die Parteien beabsichtigen, im Rahmen ihrer geschaeftlichen Zusammenarbeit vertrauliche Informationen auszutauschen. Diese Vereinbarung regelt den Umgang mit diesen Informationen.</p>
<p>Art der Vereinbarung: <strong>{{nda_type_label}}</strong></p>
</div>

<h2>§ 2 Definition vertraulicher Informationen</h2>
<div class="clause">
<p>Vertrauliche Informationen im Sinne dieser Vereinbarung sind saemtliche Informationen, die als vertraulich gekennzeichnet sind oder deren Vertraulichkeit sich aus der Natur der Information ergibt. Dies umfasst insbesondere:</p>
<p>{{confidential_info_description}}</p>
<p>Darueber hinaus: Geschaeftsgeheimnisse im Sinne des Gesetzes zum Schutz von Geschaeftsgeheimnissen (GeschGehG), technische Daten, Quellcode, Kundendaten, Geschaeftsplaene, Finanzdaten und Know-how.</p>
<p>Die Parteien erkennen an, dass die vertraulichen Informationen Geschaeftsgeheimnisse im Sinne von § 2 GeschGehG darstellen koennen und unter den Schutz des GeschGehG (Umsetzung der EU-Richtlinie 2016/943 ueber den Schutz von Geschaeftsgeheimnissen) fallen.</p>
</div>

<h2>§ 3 Pflichten</h2>
<div class="clause">
<p>Die empfangende Partei verpflichtet sich:</p>
<ul>
<li>Vertrauliche Informationen nur fuer den vereinbarten Zweck zu verwenden</li>
<li>Vertrauliche Informationen nicht an Dritte weiterzugeben</li>
<li>Angemessene Geheimhaltungsmassnahmen im Sinne von § 2 Abs. 1 lit. b GeschGehG zu treffen</li>
<li>Den Zugang auf Mitarbeiter zu beschraenken, die die Informationen benoetigen und die einer gleichwertigen Vertraulichkeitspflicht unterliegen</li>
</ul>
</div>

<h2>§ 4 Ausnahmen</h2>
<div class="clause">
<p>Die Geheimhaltungspflicht gilt nicht fuer Informationen, die:</p>
<ul>
<li>Zum Zeitpunkt der Offenlegung bereits oeffentlich bekannt waren</li>
<li>Von der empfangenden Partei nachweislich unabhaengig entwickelt wurden</li>
<li>Von einem Dritten rechtmaessig und ohne Vertraulichkeitspflicht erhalten wurden</li>
<li>Aufgrund gesetzlicher Verpflichtung oder behoerdlicher Anordnung offengelegt werden muessen — in diesem Fall ist die offenlegende Partei verpflichtet, die andere Partei unverzueglich vorab zu informieren, soweit gesetzlich zulaessig</li>
</ul>
</div>

<h2>§ 5 Laufzeit</h2>
<div class="clause">
<p>Diese Vereinbarung gilt fuer einen Zeitraum von <strong>{{duration_years}} Jahren</strong> ab Unterzeichnung. Die Geheimhaltungspflicht besteht auch nach Ablauf der Vereinbarung fuer alle waehrend der Laufzeit erhaltenen Informationen fort.</p>
</div>

<h2>§ 6 Rueckgabe und Vernichtung</h2>
<div class="clause">
<p>Auf Verlangen oder bei Beendigung der Vereinbarung sind saemtliche vertrauliche Informationen einschliesslich aller Kopien zurueckzugeben oder nachweislich zu vernichten. Die empfangende Partei hat die Vernichtung schriftlich zu bestaetigen.</p>
</div>

<h2>§ 7 Unterlassungsanspruch</h2>
<div class="clause">
<p>Bei drohendem oder tatsaechlichem Verstoss gegen diese Vereinbarung ist die geschaedigte Partei berechtigt, Unterlassung zu verlangen. Die Parteien erkennen an, dass ein Verstoss gegen diese Vereinbarung einen nicht wiedergutzumachenden Schaden verursachen kann, der durch Geldersatz allein nicht ausreichend kompensiert werden kann. Die geschaedigte Partei ist daher berechtigt, einstweiligen Rechtsschutz (einstweilige Verfuegung) zu beantragen, und zwar zusaetzlich zu allen anderen Rechtsbehelfen.</p>
</div>

{{#penalty_amount}}<h2>§ 8 Vertragsstrafe</h2>
<div class="clause">
<p>Bei Verstoss gegen diese Vereinbarung ist eine Vertragsstrafe in Hoehe von <strong>{{penalty_amount}} {{currency}}</strong> je Verstoss faellig. Die Geltendmachung weitergehender Schadensersatzansprueche bleibt unberuehrt. Auf die Vertragsstrafe wird ein etwaiger Schadensersatzanspruch angerechnet.</p>
</div>{{/penalty_amount}}

<h2>§ {{#penalty_amount}}9{{/penalty_amount}}{{^penalty_amount}}8{{/penalty_amount}} Schlussbestimmungen</h2>
<div class="clause">
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}. Aenderungen beduerfen der Schriftform.</p>
<p>Sollte eine Bestimmung dieser Vereinbarung unwirksam sein, so wird die Wirksamkeit der uebrigen Bestimmungen hiervon nicht beruehrt.</p>
</div>
', NULL, 1);

-- 10. Non-Disclosure Agreement (EN)
INSERT INTO contract_templates (id, user_id, name, contract_type, language, content_html, variables, is_default) VALUES
('tpl-nda-en', 'system', 'Non-Disclosure Agreement (NDA)', 'nda', 'en', '
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
</ul>
</div>

<h2>5. Duration</h2>
<div class="clause">
<p>This agreement is valid for a period of <strong>{{duration_years}} years</strong> from signing. The confidentiality obligation survives expiration for all information received during the term.</p>
</div>

<h2>6. Return and Destruction</h2>
<div class="clause">
<p>Upon request or termination, all Confidential Information including copies shall be returned or demonstrably destroyed. The receiving party shall confirm destruction in writing.</p>
</div>

<h2>7. Injunctive Relief</h2>
<div class="clause">
<p>In the event of a threatened or actual breach of this agreement, the aggrieved party shall be entitled to demand cessation. The Parties acknowledge that a breach of this agreement may cause irreparable harm that cannot be adequately compensated by monetary damages alone. The aggrieved party shall therefore be entitled to seek interim injunctive relief, in addition to all other remedies available.</p>
</div>

{{#penalty_amount}}<h2>8. Contractual Penalty</h2>
<div class="clause">
<p>In case of breach of this agreement, a contractual penalty of <strong>{{penalty_amount}} {{currency}}</strong> per breach is due. The assertion of further damage claims remains unaffected. Any contractual penalty paid shall be credited against damage claims.</p>
</div>{{/penalty_amount}}

<h2>{{#penalty_amount}}9{{/penalty_amount}}{{^penalty_amount}}8{{/penalty_amount}}. General Provisions</h2>
<div class="clause">
<p>This agreement is governed by the laws of {{governing_law_label}}. Jurisdiction is {{jurisdiction}}. Amendments require written form.</p>
<p>If any provision of this agreement is or becomes invalid, the validity of the remaining provisions shall not be affected.</p>
</div>
', NULL, 1);
