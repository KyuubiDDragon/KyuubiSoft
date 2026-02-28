-- Seed default contract templates (DE + EN for all 5 types)
-- These templates use {{placeholder}} syntax for variable substitution

-- 1. Softwarelizenzvertrag (DE)
INSERT INTO contract_templates (id, user_id, name, contract_type, language, content_html, variables, is_default) VALUES
('tpl-license-de', 'system', 'Softwarelizenzvertrag', 'license', 'de', '
<h2>§ 1 Vertragsgegenstand</h2>
<div class="clause">
<p>Der Auftragnehmer raeumt dem Auftraggeber eine {{license_type_label}} Lizenz zur Nutzung der Software <strong>{{software_name}}</strong> (Version {{software_version}}) ein.</p>
<p>Die Lizenz umfasst die Nutzung durch maximal <strong>{{max_users}}</strong> Nutzer im Gebiet <strong>{{territory_label}}</strong>.</p>
</div>

<h2>§ 2 Nutzungsrechte</h2>
<div class="clause">
<p>Der Auftraggeber erhaelt das Recht, die Software fuer eigene geschaeftliche Zwecke zu nutzen. Eine Unterlizenzierung oder Weitergabe an Dritte ist ohne schriftliche Zustimmung des Auftragnehmers nicht gestattet.</p>
<p>{{#source_code_access}}Der Auftraggeber erhaelt Zugang zum Quellcode der Software.{{/source_code_access}}{{^source_code_access}}Der Quellcode wird nicht uebergeben.{{/source_code_access}}</p>
<p>{{#modification_rights}}Der Auftraggeber ist berechtigt, die Software fuer eigene Zwecke anzupassen.{{/modification_rights}}{{^modification_rights}}Aenderungen an der Software sind nicht gestattet.{{/modification_rights}}</p>
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
<p>Der Auftragnehmer gewaehrleistet, dass die Software im Wesentlichen der Dokumentation entspricht. Die Gewaehrleistungsfrist betraegt 12 Monate ab Lieferung. Die Gewaehrleistung umfasst die Nachbesserung oder Ersatzlieferung nach Wahl des Auftragnehmers.</p>
</div>

<h2>§ 6 Haftung</h2>
<div class="clause">
<p>Die Haftung des Auftragnehmers ist auf Vorsatz und grobe Fahrlaessigkeit beschraenkt. Bei leichter Fahrlaessigkeit haftet der Auftragnehmer nur bei Verletzung wesentlicher Vertragspflichten (Kardinalpflichten), begrenzt auf den vorhersehbaren, vertragstypischen Schaden. Die Haftung ist in jedem Fall auf die Hoehe der Lizenzgebuehr beschraenkt.</p>
</div>

<h2>§ 7 Laufzeit und Kuendigung</h2>
<div class="clause">
<p>Der Vertrag beginnt am {{start_date}} {{#end_date}}und endet am {{end_date}}{{/end_date}}{{^end_date}}und laeuft auf unbestimmte Zeit{{/end_date}}.</p>
<p>Die Kuendigungsfrist betraegt {{notice_period_days}} Tage zum Ende der jeweiligen Vertragslaufzeit.</p>
</div>

<h2>§ 8 Schlussbestimmungen</h2>
<div class="clause">
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}.</p>
<p>Aenderungen und Ergaenzungen dieses Vertrages beduerfen der Schriftform.</p>
<p>Sollte eine Bestimmung dieses Vertrages unwirksam sein, bleibt die Wirksamkeit der uebrigen Bestimmungen unberuehrt.</p>
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

<h2>2. Usage Rights</h2>
<div class="clause">
<p>The Licensee is entitled to use the software for its own business purposes. Sublicensing or transfer to third parties requires prior written consent from the Licensor.</p>
<p>{{#source_code_access}}The Licensee receives access to the source code.{{/source_code_access}}{{^source_code_access}}Source code is not provided.{{/source_code_access}}</p>
<p>{{#modification_rights}}The Licensee may modify the software for own purposes.{{/modification_rights}}{{^modification_rights}}Modifications to the software are not permitted.{{/modification_rights}}</p>
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
<p>The Licensor warrants that the software substantially conforms to its documentation. The warranty period is 12 months from delivery. The warranty covers repair or replacement at the Licensor''s discretion.</p>
</div>

<h2>6. Limitation of Liability</h2>
<div class="clause">
<p>The Licensor''s liability is limited to willful misconduct and gross negligence. In cases of slight negligence, liability is limited to breach of essential contractual obligations and the foreseeable, contract-typical damage. In any case, liability is limited to the amount of the license fee.</p>
</div>

<h2>7. Term and Termination</h2>
<div class="clause">
<p>This agreement commences on {{start_date}} {{#end_date}}and terminates on {{end_date}}{{/end_date}}{{^end_date}}and continues for an indefinite period{{/end_date}}.</p>
<p>The notice period is {{notice_period_days}} days before the end of the respective contract period.</p>
</div>

<h2>8. General Provisions</h2>
<div class="clause">
<p>This agreement is governed by the laws of {{governing_law_label}}. The place of jurisdiction is {{jurisdiction}}.</p>
<p>Amendments to this agreement must be made in writing.</p>
<p>If any provision of this agreement is invalid, the remaining provisions shall remain in effect.</p>
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

<h2>§ 3 Verguetung</h2>
<div class="clause">
<p>Die Verguetung betraegt <strong>{{total_value}} {{currency}}</strong> und wird {{payment_schedule_label}} abgerechnet.</p>
</div>

<h2>§ 4 Abnahme</h2>
<div class="clause">
<p>{{acceptance_procedure}}</p>
<p>Der Auftraggeber hat die Software innerhalb von 14 Tagen nach Uebergabe zu pruefen und abzunehmen oder Maengel schriftlich zu ruegen. Die Abnahme gilt als erfolgt, wenn der Auftraggeber die Software ohne wesentliche Beanstandungen produktiv einsetzt.</p>
</div>

<h2>§ 5 Urheberrecht und Nutzungsrechte</h2>
<div class="clause">
<p>Das Urheberrecht an der Software verbleibt beim Auftragnehmer. Der Auftraggeber erhaelt ein einfaches, nicht uebertragbares Nutzungsrecht fuer eigene geschaeftliche Zwecke.</p>
<p>Der Quellcode wird nach vollstaendiger Bezahlung an den Auftraggeber uebergeben.</p>
</div>

<h2>§ 6 Gewaehrleistung</h2>
<div class="clause">
<p>Die Gewaehrleistungsfrist betraegt {{warranty_months}} Monate ab Abnahme. Der Auftragnehmer ist zur Nachbesserung verpflichtet. Bei Fehlschlagen der Nachbesserung (nach zwei Versuchen) kann der Auftraggeber Minderung oder Ruecktritt verlangen.</p>
</div>

<h2>§ 7 Geheimhaltung</h2>
<div class="clause">
<p>Beide Parteien verpflichten sich, vertrauliche Informationen der jeweils anderen Partei nicht an Dritte weiterzugeben. Diese Pflicht besteht auch nach Vertragsende fort.</p>
</div>

<h2>§ 8 Haftung</h2>
<div class="clause">
<p>Die Haftung des Auftragnehmers ist auf die Hoehe der vereinbarten Verguetung beschraenkt. Dies gilt nicht fuer Vorsatz und grobe Fahrlaessigkeit sowie fuer Schaeden an Leben, Koerper oder Gesundheit.</p>
</div>

<h2>§ 9 Schlussbestimmungen</h2>
<div class="clause">
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}. Aenderungen beduerfen der Schriftform. Das Uebereinkommen der Vereinten Nationen ueber Vertraege ueber den internationalen Warenkauf (CISG) findet keine Anwendung.</p>
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
<p>Development shall proceed according to the agreed milestones. The Developer owes a functional work in accordance with the agreed specifications.</p>
</div>

<h2>3. Compensation</h2>
<div class="clause">
<p>The total compensation amounts to <strong>{{total_value}} {{currency}}</strong>, payable {{payment_schedule_label}}.</p>
</div>

<h2>4. Acceptance</h2>
<div class="clause">
<p>{{acceptance_procedure}}</p>
<p>The Client shall review and accept the software within 14 days of delivery, or report defects in writing. Acceptance is deemed granted if the Client uses the software productively without material objections.</p>
</div>

<h2>5. Intellectual Property</h2>
<div class="clause">
<p>Copyright in the software remains with the Developer. The Client receives a non-exclusive, non-transferable license to use the software for its own business purposes.</p>
<p>Source code shall be transferred to the Client upon full payment.</p>
</div>

<h2>6. Warranty</h2>
<div class="clause">
<p>The warranty period is {{warranty_months}} months from acceptance. The Developer is obligated to remedy defects. If remediation fails (after two attempts), the Client may request a reduction or rescission.</p>
</div>

<h2>7. Confidentiality</h2>
<div class="clause">
<p>Both parties agree to keep confidential information of the other party secret. This obligation survives termination of this agreement.</p>
</div>

<h2>8. Limitation of Liability</h2>
<div class="clause">
<p>The Developer''s liability is limited to the agreed compensation. This does not apply to willful misconduct, gross negligence, or damages to life, body, or health.</p>
</div>

<h2>9. General Provisions</h2>
<div class="clause">
<p>This agreement is governed by the laws of {{governing_law_label}}. Jurisdiction is {{jurisdiction}}. Amendments require written form. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.</p>
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
<p>Die Verarbeitung personenbezogener Daten erfolgt gemaess DSGVO. Ein separater Auftragsverarbeitungsvertrag (AVV) gemaess Art. 28 DSGVO ist Bestandteil dieses Vertrages. Daten werden ausschliesslich in {{data_location_label}} gespeichert.</p>
</div>

<h2>§ 5 Verguetung</h2>
<div class="clause">
<p>Die Nutzungsgebuehr betraegt <strong>{{price_per_period}} {{currency}}</strong> pro {{subscription_model_label}} und ist im Voraus faellig.</p>
</div>

<h2>§ 6 Laufzeit und Kuendigung</h2>
<div class="clause">
<p>Der Vertrag beginnt am {{start_date}} und laeuft {{subscription_model_label}}. Die Kuendigungsfrist betraegt {{notice_period_days}} Tage zum Ende der jeweiligen Abrechnungsperiode.</p>
<p>{{#auto_renewal}}Der Vertrag verlaengert sich automatisch um jeweils einen weiteren Abrechnungszeitraum.{{/auto_renewal}}</p>
</div>

<h2>§ 7 Datenexport und Vertragsende</h2>
<div class="clause">
<p>Bei Vertragsende stellt der Anbieter dem Kunden seine Daten in einem gaengigen Format (z.B. CSV, JSON) fuer den Export zur Verfuegung. Die Daten werden 30 Tage nach Vertragsende geloescht.</p>
</div>

<h2>§ 8 Haftung</h2>
<div class="clause">
<p>Die Haftung des Anbieters ist auf die in den letzten 12 Monaten gezahlten Nutzungsgebuehren beschraenkt. Dies gilt nicht fuer Vorsatz und grobe Fahrlaessigkeit.</p>
</div>

<h2>§ 9 Schlussbestimmungen</h2>
<div class="clause">
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}.</p>
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

<h2>4. Data Protection</h2>
<div class="clause">
<p>Processing of personal data is carried out in accordance with GDPR. A separate Data Processing Agreement (DPA) pursuant to Art. 28 GDPR forms part of this agreement. Data is stored exclusively in {{data_location_label}}.</p>
</div>

<h2>5. Fees</h2>
<div class="clause">
<p>The usage fee amounts to <strong>{{price_per_period}} {{currency}}</strong> per {{subscription_model_label}}, payable in advance.</p>
</div>

<h2>6. Term and Termination</h2>
<div class="clause">
<p>This agreement commences on {{start_date}} and runs on a {{subscription_model_label}} basis. The notice period is {{notice_period_days}} days before the end of the respective billing period.</p>
<p>{{#auto_renewal}}The agreement automatically renews for an additional billing period.{{/auto_renewal}}</p>
</div>

<h2>7. Data Export and Termination</h2>
<div class="clause">
<p>Upon termination, the Provider shall make the Customer''s data available for export in a common format (e.g., CSV, JSON). Data will be deleted 30 days after contract termination.</p>
</div>

<h2>8. Limitation of Liability</h2>
<div class="clause">
<p>The Provider''s liability is limited to the fees paid in the preceding 12 months. This does not apply to willful misconduct and gross negligence.</p>
</div>

<h2>9. General Provisions</h2>
<div class="clause">
<p>This agreement is governed by the laws of {{governing_law_label}}. Jurisdiction is {{jurisdiction}}.</p>
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

<h2>§ 4 Verguetung</h2>
<div class="clause">
<p>Die Wartungsgebuehr betraegt <strong>{{total_value}} {{currency}}</strong> und wird {{payment_schedule_label}} abgerechnet. Leistungen ueber das vereinbarte Kontingent hinaus werden nach Aufwand abgerechnet.</p>
</div>

<h2>§ 5 Laufzeit und Kuendigung</h2>
<div class="clause">
<p>Der Vertrag beginnt am {{start_date}} und laeuft auf unbestimmte Zeit. Die Kuendigungsfrist betraegt {{notice_period_days}} Tage zum Monatsende.</p>
</div>

<h2>§ 6 Haftung</h2>
<div class="clause">
<p>Die Haftung ist auf die jaehrliche Wartungsgebuehr beschraenkt. Ausgenommen sind Schaeden durch Vorsatz oder grobe Fahrlaessigkeit.</p>
</div>

<h2>§ 7 Schlussbestimmungen</h2>
<div class="clause">
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}. Aenderungen beduerfen der Schriftform.</p>
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

<h2>4. Fees</h2>
<div class="clause">
<p>The maintenance fee amounts to <strong>{{total_value}} {{currency}}</strong>, payable {{payment_schedule_label}}. Services beyond the agreed scope are billed at hourly rates.</p>
</div>

<h2>5. Term and Termination</h2>
<div class="clause">
<p>This agreement commences on {{start_date}} and continues indefinitely. The notice period is {{notice_period_days}} days to the end of the month.</p>
</div>

<h2>6. Limitation of Liability</h2>
<div class="clause">
<p>Liability is limited to the annual maintenance fee. This excludes damages caused by willful misconduct or gross negligence.</p>
</div>

<h2>7. General Provisions</h2>
<div class="clause">
<p>This agreement is governed by the laws of {{governing_law_label}}. Jurisdiction is {{jurisdiction}}. Amendments require written form.</p>
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
<p>Darueber hinaus: Geschaeftsgeheimnisse, technische Daten, Quellcode, Kundendaten, Geschaeftsplaene, Finanzdaten und Know-how.</p>
</div>

<h2>§ 3 Pflichten</h2>
<div class="clause">
<p>Die empfangende Partei verpflichtet sich:</p>
<ul>
<li>Vertrauliche Informationen nur fuer den vereinbarten Zweck zu verwenden</li>
<li>Vertrauliche Informationen nicht an Dritte weiterzugeben</li>
<li>Angemessene Massnahmen zum Schutz der Vertraulichkeit zu treffen</li>
<li>Den Zugang auf Mitarbeiter zu beschraenken, die die Informationen benoetigen</li>
</ul>
</div>

<h2>§ 4 Ausnahmen</h2>
<div class="clause">
<p>Die Geheimhaltungspflicht gilt nicht fuer Informationen, die:</p>
<ul>
<li>Zum Zeitpunkt der Offenlegung bereits oeffentlich bekannt waren</li>
<li>Von der empfangenden Partei nachweislich unabhaengig entwickelt wurden</li>
<li>Von einem Dritten rechtmaessig und ohne Vertraulichkeitspflicht erhalten wurden</li>
<li>Aufgrund gesetzlicher Verpflichtung offengelegt werden muessen</li>
</ul>
</div>

<h2>§ 5 Laufzeit</h2>
<div class="clause">
<p>Diese Vereinbarung gilt fuer einen Zeitraum von <strong>{{duration_years}} Jahren</strong> ab Unterzeichnung. Die Geheimhaltungspflicht besteht auch nach Ablauf der Vereinbarung fuer alle waehrend der Laufzeit erhaltenen Informationen fort.</p>
</div>

<h2>§ 6 Rueckgabe und Vernichtung</h2>
<div class="clause">
<p>Auf Verlangen oder bei Beendigung der Vereinbarung sind saemtliche vertrauliche Informationen einschliesslich aller Kopien zurueckzugeben oder nachweislich zu vernichten.</p>
</div>

{{#penalty_amount}}<h2>§ 7 Vertragsstrafe</h2>
<div class="clause">
<p>Bei Verstoss gegen diese Vereinbarung ist eine Vertragsstrafe in Hoehe von <strong>{{penalty_amount}} {{currency}}</strong> je Verstoss faellig. Die Geltendmachung weitergehender Schadensersatzansprueche bleibt unberuehrt.</p>
</div>{{/penalty_amount}}

<h2>§ 8 Schlussbestimmungen</h2>
<div class="clause">
<p>Es gilt das Recht der {{governing_law_label}}. Gerichtsstand ist {{jurisdiction}}. Aenderungen beduerfen der Schriftform.</p>
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
<p>Furthermore: trade secrets, technical data, source code, customer data, business plans, financial data, and know-how.</p>
</div>

<h2>3. Obligations</h2>
<div class="clause">
<p>The receiving party undertakes to:</p>
<ul>
<li>Use Confidential Information only for the agreed purpose</li>
<li>Not disclose Confidential Information to third parties</li>
<li>Take reasonable measures to protect confidentiality</li>
<li>Restrict access to employees who need the information</li>
</ul>
</div>

<h2>4. Exceptions</h2>
<div class="clause">
<p>The confidentiality obligation does not apply to information that:</p>
<ul>
<li>Was publicly known at the time of disclosure</li>
<li>Was demonstrably developed independently by the receiving party</li>
<li>Was lawfully received from a third party without confidentiality obligations</li>
<li>Must be disclosed due to legal obligations</li>
</ul>
</div>

<h2>5. Duration</h2>
<div class="clause">
<p>This agreement is valid for a period of <strong>{{duration_years}} years</strong> from signing. The confidentiality obligation survives expiration for all information received during the term.</p>
</div>

<h2>6. Return and Destruction</h2>
<div class="clause">
<p>Upon request or termination, all Confidential Information including copies shall be returned or demonstrably destroyed.</p>
</div>

{{#penalty_amount}}<h2>7. Contractual Penalty</h2>
<div class="clause">
<p>In case of breach of this agreement, a contractual penalty of <strong>{{penalty_amount}} {{currency}}</strong> per breach is due. The assertion of further damage claims remains unaffected.</p>
</div>{{/penalty_amount}}

<h2>8. General Provisions</h2>
<div class="clause">
<p>This agreement is governed by the laws of {{governing_law_label}}. Jurisdiction is {{jurisdiction}}. Amendments require written form.</p>
</div>
', NULL, 1);
