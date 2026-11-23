<?php
/**
 * Paket `cc-fx-rk-v1`: drei neutrale Referenzkurse für die Nutzertests (R0-26 §1.1).
 *
 * Eigene, synthetische Inhalte unter CC0 (E-01, E-02, R0-26 §0.2 Nr. 3):
 * fachneutral, ohne Bezug auf einen Fremdanbieter, jede Frage ist aus dem
 * Lektionstext beantwortbar. Firmen, Personen und Adressen sind erfunden
 * (`example.test`). Nur für Test-Sites.
 *
 * Fragen (positionsbasiert, optional mit `img` als letztem Element):
 *   ['s',  Text, richtig, [falsch …]]                single_choice
 *   ['m',  Text, [richtig …], [falsch …]]            multi_choice
 *   ['tf', Aussage, bool]                            true_false
 *   ['o',  Text, Musterantwort]                      open_text
 *   ['f',  Vorderseite, Rückseite]                   flashcard
 *   ['ma', Text, [links => rechts …]]                matching
 *   ['or', Text, [Schritt 1, Schritt 2 …]]           ordering (Lösungsreihenfolge)
 *   ['cl', Text mit {{g1}}, [g1 => [akzeptiert …]]]  cloze
 *   ['n',  Text, Sollwert, absolute|relative, Toleranz, Einheit]  numeric_tolerance
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

return array(
	'package' => 'cc-fx-rk-v1',
	'license' => 'CC0-1.0',
	'courses' => array(

		// ------------------------------------------------------------------
		// RK-1 Selbstlernkurs.
		// ------------------------------------------------------------------
		'rk1' => array(
			'id'          => 'RK-1',
			'slug'        => 'cc-fx-rk1',
			'title'       => 'Zeitmanagement im Alltag',
			'summary'     => 'Ein Selbstlernkurs mit einfachen Methoden, um Aufgaben zu planen und Prioritäten zu setzen.',
			'objectives'  => array(
				'Ziele nach der SMART-Regel formulieren',
				'Aufgaben mit der Eisenhower-Matrix priorisieren',
				'Einen Wochenplan mit Pufferzeiten erstellen',
			),
			'access'      => array(
				'mode'        => 'free_limited',
				'first_items' => 3,
			),
			'exam'        => array(
				'version'    => 'Abschlussquiz',
				'blueprint'  => array( 7, 7, 6 ),
				'time_limit' => null,
				'pass'       => array( 7, 10 ),
				'parts'      => null,
				'attempts'   => null,
				'timer_mode' => 'strict',
				'feedback'   => 'after_submit',
			),
			'certificate' => 'Ausstellung bei Abschluss und bestandenem Quiz',
			'modules'     => array(
				array(
					'title'     => 'Grundlagen',
					'lessons'   => array(
						array( 'l01', 'Warum Zeitmanagement?', 'lesson', '<p>Zeitmanagement bedeutet, die eigene Zeit bewusst nach Prioritäten einzuteilen. Das Ziel ist nicht, mehr zu arbeiten, sondern das Wichtige zuerst zu erledigen. Wer seine Woche plant, erlebt weniger Stress.</p><p>Typische Zeitdiebe sind unklare Ziele, ständige Unterbrechungen und fehlende Pausen. Ein Zeitprotokoll über eine Woche zeigt, wofür die Zeit tatsächlich verwendet wird.</p>' ),
						array( 'l02', 'Ziele formulieren', 'lesson', '<p>Gute Ziele sind SMART: <strong>s</strong>pezifisch, <strong>m</strong>essbar, <strong>a</strong>ttraktiv, <strong>r</strong>ealistisch und <strong>t</strong>erminiert.</p><p>„Bis Freitag lese ich zwei Kapitel“ ist messbar und terminiert. „Mehr lesen“ ist es nicht. Große Ziele werden in Teilziele zerlegt und schriftlich festgehalten.</p>' ),
						array( 'l03', 'Die Eisenhower-Matrix', 'video_internal', '<p>Die Eisenhower-Matrix ordnet Aufgaben nach Wichtigkeit und Dringlichkeit in vier Felder:</p><ul><li>A – wichtig und dringend: sofort selbst erledigen.</li><li>B – wichtig, nicht dringend: einen Termin dafür festlegen.</li><li>C – dringend, nicht wichtig: abgeben (delegieren).</li><li>D – weder wichtig noch dringend: streichen.</li></ul><p>Die meisten Aufgaben, die zu langfristigen Zielen führen, liegen im Feld B.</p>' ),
						array( 'l04', 'Wochenplan-Vorlage', 'file', '<p>Die Vorlage (PDF) hat sieben Spalten für die Wochentage und drei Zeilen: Termine, Aufgaben und Puffer. Geplant wird einmal pro Woche, zum Beispiel am Freitagnachmittag, und das dauert etwa 15 Minuten.</p>' ),
					),
					'questions' => array(
						array( 's', 'Worum geht es beim Zeitmanagement vor allem?', 'Das Wichtige zuerst zu erledigen', array( 'Möglichst viele Stunden zu arbeiten', 'Jede Aufgabe sofort zu erledigen', 'Auf Pausen zu verzichten' ) ),
						array( 's', 'Was zeigt ein Zeitprotokoll über eine Woche?', 'Wofür die Zeit tatsächlich verwendet wird', array( 'Wie viele Termine man nächste Woche hat', 'Welche Ziele SMART sind', 'Wie lange eine Pause dauern sollte' ) ),
						array( 's', 'Wofür steht das „T“ in SMART?', 'terminiert', array( 'teilbar', 'typisch', 'täglich' ) ),
						array( 's', 'Welches Ziel ist SMART formuliert?', 'Bis Freitag lese ich zwei Kapitel.', array( 'Ich will mehr lesen.', 'Lesen ist wichtig.', 'Irgendwann lese ich das Buch.' ) ),
						array( 's', 'Was tun Sie mit einer Aufgabe, die wichtig, aber nicht dringend ist?', 'Einen Termin dafür festlegen', array( 'Sofort selbst erledigen', 'Abgeben', 'Streichen' ) ),
						array(
							's',
							'Das Bild zeigt die Eisenhower-Matrix. Welches Feld steht oben links?',
							'wichtig und dringend',
							array( 'wichtig, nicht dringend', 'dringend, nicht wichtig', 'weder wichtig noch dringend' ),
							'img' => 'rk1-matrix',
						),
						array( 's', 'Wie viele Zeilen hat die Wochenplan-Vorlage?', 'Drei', array( 'Zwei', 'Fünf', 'Sieben' ) ),
						array( 's', 'Wie lange dauert die wöchentliche Planung mit der Vorlage etwa?', '15 Minuten', array( '2 Minuten', '1 Stunde', 'einen halben Tag' ) ),
						array( 'm', 'Welche Zeitdiebe nennt die Lektion? (mehrere richtig)', array( 'Unklare Ziele', 'Ständige Unterbrechungen', 'Fehlende Pausen' ), array( 'Ein Wochenplan', 'Ein Zeitprotokoll' ) ),
						array( 'm', 'Welche Eigenschaften gehören zu SMART? (mehrere richtig)', array( 'messbar', 'realistisch' ), array( 'spontan', 'mündlich' ) ),
						array( 'm', 'Welche Zeilen hat die Wochenplan-Vorlage? (mehrere richtig)', array( 'Termine', 'Aufgaben', 'Puffer' ), array( 'Notizen', 'Telefonnummern' ) ),
						array( 'tf', 'Große Ziele sollten in Teilziele zerlegt werden.', true ),
						array( 'tf', 'Aufgaben im Feld D der Eisenhower-Matrix erledigt man sofort selbst.', false ),
						array( 'o', 'Erklären Sie in einem Satz, warum Planung Stress verringern kann.', 'Wer plant, weiß, was als Nächstes wichtig ist, und wird seltener von Aufgaben überrascht.' ),
						array( 'o', 'Nennen Sie ein Beispiel für ein SMART formuliertes Ziel.', 'Zum Beispiel: „Bis Ende des Monats gehe ich jeden Dienstag 30 Minuten spazieren.“' ),
						array( 'f', 'SMART', 'spezifisch, messbar, attraktiv, realistisch, terminiert' ),
						array( 'f', 'Feld C der Eisenhower-Matrix', 'dringend, nicht wichtig: abgeben (delegieren)' ),
						array(
							'ma',
							'Ordnen Sie jedem Feld der Eisenhower-Matrix die passende Handlung zu.',
							array(
								'wichtig und dringend'    => 'sofort selbst erledigen',
								'wichtig, nicht dringend' => 'Termin festlegen',
								'dringend, nicht wichtig' => 'abgeben',
								'weder wichtig noch dringend' => 'streichen',
							),
						),
					),
				),
				array(
					'title'     => 'Planen',
					'lessons'   => array(
						array( 'l05', 'Die ALPEN-Methode', 'lesson', '<p>Die ALPEN-Methode hilft bei der Tagesplanung in fünf Schritten: <strong>A</strong>ufgaben notieren, <strong>L</strong>änge schätzen, <strong>P</strong>ufferzeit einplanen, <strong>E</strong>ntscheidungen treffen (Prioritäten setzen) und <strong>N</strong>achkontrolle.</p><p>Verplant wird nur etwa 60 Prozent der Zeit. Die übrigen 40 Prozent bleiben frei für Unvorhergesehenes und spontane Aufgaben.</p>' ),
						array( 'l06', 'Pufferzeiten', 'video_external', '<p>Unvorhergesehenes passiert jeden Tag. Zwischen zwei Terminen sollten 10 bis 15 Minuten Puffer liegen. Als Faustregel gilt 60 zu 40: 60 Prozent geplante Zeit, 40 Prozent Puffer. Wer keine Puffer plant, muss Aufgaben ständig verschieben.</p>' ),
						array( 'l07', 'Das Pareto-Prinzip', 'lesson', '<p>Das Pareto-Prinzip besagt: Mit etwa 20 Prozent des Aufwands erreicht man oft 80 Prozent des Ergebnisses. Perfektionismus kostet viel Zeit für wenig zusätzlichen Nutzen.</p><p>Eine hilfreiche Frage lautet daher: Welche Aufgaben bringen den größten Nutzen?</p>' ),
					),
					'questions' => array(
						array( 's', 'Wofür steht das „L“ in der ALPEN-Methode?', 'Länge schätzen', array( 'Liste löschen', 'Lernziel setzen', 'Leistung messen' ) ),
						array( 's', 'Wie viel Prozent der Zeit sollte man nach der ALPEN-Methode verplanen?', 'etwa 60 Prozent', array( 'etwa 20 Prozent', 'etwa 80 Prozent', '100 Prozent' ) ),
						array( 's', 'Wie viel Puffer empfiehlt die Lektion zwischen zwei Terminen?', '10 bis 15 Minuten', array( '1 bis 2 Minuten', '45 Minuten', 'eine Stunde' ) ),
						array( 's', 'Was passiert, wenn man keine Puffer plant?', 'Man muss Aufgaben ständig verschieben.', array( 'Man wird schneller fertig.', 'Man hat mehr Freizeit.', 'Nichts, Puffer sind unnötig.' ) ),
						array( 's', 'Was besagt das Pareto-Prinzip?', 'Mit 20 Prozent Aufwand erreicht man oft 80 Prozent des Ergebnisses.', array( 'Jede Aufgabe braucht gleich viel Zeit.', 'Man sollte 80 Prozent der Zeit verplanen.', 'Pausen sind nach 20 Minuten nötig.' ) ),
						array( 's', 'Welche Frage hilft nach dem Pareto-Prinzip?', 'Welche Aufgaben bringen den größten Nutzen?', array( 'Welche Aufgabe ist am kürzesten?', 'Welche Aufgabe macht am meisten Spaß?', 'Welche Aufgabe ist am ältesten?' ) ),
						array( 's', 'Was ist der letzte Schritt der ALPEN-Methode?', 'Nachkontrolle', array( 'Aufgaben notieren', 'Pufferzeit einplanen', 'Entscheidungen treffen' ) ),
						array( 'm', 'Welche Schritte gehören zur ALPEN-Methode? (mehrere richtig)', array( 'Aufgaben notieren', 'Pufferzeit einplanen', 'Entscheidungen treffen' ), array( 'Alles sofort erledigen', 'Pausen streichen' ) ),
						array( 'm', 'Wofür sind die 40 Prozent freie Zeit gedacht? (mehrere richtig)', array( 'Unvorhergesehenes', 'Spontane Aufgaben' ), array( 'Zusätzliche feste Termine', 'Perfektionismus' ) ),
						array( 'm', 'Welche Aussagen zum Perfektionismus stimmen laut Lektion? (mehrere richtig)', array( 'Er kostet viel Zeit.', 'Er bringt oft wenig zusätzlichen Nutzen.' ), array( 'Er spart Zeit.', 'Er ist immer notwendig.' ) ),
						array( 'tf', 'Nach der Faustregel 60 zu 40 sind 40 Prozent der Zeit Puffer.', true ),
						array( 'tf', 'Das „E“ in ALPEN steht für „Erholung“.', false ),
						array( 'o', 'Warum sollte man nicht 100 Prozent seiner Zeit verplanen?', 'Weil jeden Tag Unvorhergesehenes passiert und sonst Aufgaben verschoben werden müssen.' ),
						array( 'f', 'Pareto-Prinzip', '20 Prozent Aufwand bringen oft 80 Prozent des Ergebnisses' ),
						array(
							'ma',
							'Ordnen Sie den Buchstaben der ALPEN-Methode den Schritt zu.',
							array(
								'A' => 'Aufgaben notieren',
								'L' => 'Länge schätzen',
								'P' => 'Pufferzeit einplanen',
								'N' => 'Nachkontrolle',
							),
						),
						array( 'or', 'Bringen Sie die Schritte der ALPEN-Methode in die richtige Reihenfolge.', array( 'Aufgaben notieren', 'Länge schätzen', 'Pufferzeit einplanen', 'Entscheidungen treffen', 'Nachkontrolle' ) ),
					),
				),
				array(
					'title'     => 'Umsetzen',
					'lessons'   => array(
						array( 'l08', 'Ablenkungen reduzieren', 'lesson', '<p>Benachrichtigungen auf Telefon und Computer lenken ab. Schalten Sie sie in Arbeitsphasen aus und lesen Sie E-Mails zu festen Zeiten, zum Beispiel zwei- bis dreimal am Tag. Legen Sie das Telefon außer Reichweite.</p><p>Multitasking senkt die Konzentration. Nach einer Unterbrechung dauert es mehrere Minuten, bis man wieder in die Aufgabe hineinfindet. Feste „Nicht stören“-Zeiten helfen.</p>' ),
						array( 'l09', 'Pausen und Pomodoro', 'lesson', '<p>Bei der Pomodoro-Technik arbeitet man 25 Minuten konzentriert und macht danach 5 Minuten Pause. Nach vier solchen Einheiten folgt eine längere Pause von 15 bis 30 Minuten.</p><p>Pausen steigern die Leistung, besonders mit kurzer Bewegung. Viele Menschen haben ihr Leistungshoch am Vormittag.</p>' ),
						array( 'l10', 'Aufgabe: Mein Wochenplan', 'assignment', '<p>In dieser Aufgabe wenden Sie die Methoden des Kurses auf Ihre eigene Woche an.</p>' ),
					),
					'questions' => array(
						array( 's', 'Wie oft am Tag sollte man laut Lektion E-Mails lesen?', 'zwei- bis dreimal zu festen Zeiten', array( 'sofort bei jeder neuen E-Mail', 'einmal pro Woche', 'jede Viertelstunde' ) ),
						array( 's', 'Wie wirkt sich Multitasking auf die Konzentration aus?', 'Es senkt die Konzentration.', array( 'Es steigert die Konzentration.', 'Es hat keinen Einfluss.', 'Es ersetzt Pausen.' ) ),
						array( 's', 'Wie lange dauert eine Arbeitseinheit bei der Pomodoro-Technik?', '25 Minuten', array( '10 Minuten', '45 Minuten', '90 Minuten' ) ),
						array( 's', 'Wann folgt bei der Pomodoro-Technik eine längere Pause?', 'Nach vier Einheiten', array( 'Nach jeder Einheit', 'Nach zwei Einheiten', 'Nur am Ende des Tages' ) ),
						array( 's', 'Wann haben viele Menschen ihr Leistungshoch?', 'Am Vormittag', array( 'Direkt nach dem Mittagessen', 'Am späten Abend', 'In der Nacht' ) ),
						array( 's', 'Was hilft gegen Ablenkung durch das Telefon?', 'Das Telefon außer Reichweite legen', array( 'Den Klingelton lauter stellen', 'Öfter auf das Display schauen', 'Alle Nachrichten sofort beantworten' ) ),
						array(
							's',
							'Das Bild ist eine Verzierung. Wie lange dauert die kurze Pause nach einer Pomodoro-Einheit?',
							'5 Minuten',
							array( '1 Minute', '15 Minuten', '30 Minuten' ),
							'img' => 'rk1-deko',
						),
						array( 'm', 'Welche Maßnahmen gegen Ablenkung nennt die Lektion? (mehrere richtig)', array( 'Benachrichtigungen ausschalten', 'E-Mails zu festen Zeiten lesen', 'Feste „Nicht stören“-Zeiten' ), array( 'Mehrere Aufgaben gleichzeitig erledigen', 'Pausen streichen' ) ),
						array( 'm', 'Welche Aussagen zu Pausen stimmen laut Lektion? (mehrere richtig)', array( 'Pausen steigern die Leistung.', 'Kurze Bewegung in der Pause hilft.' ), array( 'Pausen sind verlorene Zeit.', 'Pausen sollte man erst am Abend machen.' ) ),
						array( 'm', 'Wie lang kann die längere Pomodoro-Pause sein? (mehrere richtig)', array( '15 Minuten', '30 Minuten' ), array( '2 Minuten', '2 Stunden' ) ),
						array( 'tf', 'Nach einer Unterbrechung ist man sofort wieder voll konzentriert.', false ),
						array( 'o', 'Beschreiben Sie den Ablauf der Pomodoro-Technik.', '25 Minuten konzentriert arbeiten, 5 Minuten Pause; nach vier Einheiten eine längere Pause von 15 bis 30 Minuten.' ),
						array( 'f', 'Pomodoro-Einheit', '25 Minuten Arbeit, danach 5 Minuten Pause' ),
						array( 'f', 'Längere Pomodoro-Pause', 'nach vier Einheiten, 15 bis 30 Minuten' ),
						array( 'or', 'Bringen Sie einen Pomodoro-Durchlauf in die richtige Reihenfolge.', array( '25 Minuten arbeiten', '5 Minuten Pause', 'Vier Einheiten wiederholen', 'Längere Pause machen' ) ),
						array(
							'cl',
							'Bei der Pomodoro-Technik arbeitet man {{g1}} Minuten und macht danach {{g2}} Minuten Pause.',
							array(
								'g1' => array( '25', 'fünfundzwanzig' ),
								'g2' => array( '5', 'fünf' ),
							),
						),
					),
				),
			),
			'assignment'  => array(
				'lesson'          => 'l10',
				'submission_type' => 'text',
				'max_points'      => 10,
				'passing_points'  => 6,
				'completion'      => 'graded',
				'instructions'    => 'Erstellen Sie Ihren Wochenplan für die nächste Woche: Nennen Sie drei Ziele nach der SMART-Regel, ordnen Sie fünf Aufgaben den Feldern der Eisenhower-Matrix zu und beschreiben Sie, wo Sie Pufferzeiten einplanen. Umfang: 150 bis 1500 Zeichen.',
				'rubric_text'     => 'Je Ziel nach SMART bis 2 Punkte (6), Matrix-Zuordnung bis 2 Punkte, Pufferzeiten bis 2 Punkte.',
				'text_limits'     => array(
					'min_chars' => 150,
					'max_chars' => 1500,
				),
			),
			'images'      => array(
				'rk1-matrix' => array(
					'kind' => 'matrix',
					'alt'  => 'Eisenhower-Matrix mit vier Feldern: oben links wichtig und dringend, oben rechts wichtig und nicht dringend, unten links dringend und nicht wichtig, unten rechts weder wichtig noch dringend.',
				),
				'rk1-deko'   => array(
					'kind' => 'decorative',
					'alt'  => '',
				),
			),
		),

		// ------------------------------------------------------------------
		// RK-2 Prüfungsvorbereitung.
		// ------------------------------------------------------------------
		'rk2' => array(
			'id'          => 'RK-2',
			'slug'        => 'cc-fx-rk2',
			'title'       => 'Grundlagen der Tabellenkalkulation – Prüfungstraining',
			'summary'     => 'Prüfungstraining zu Zellbezügen, Formeln, Sortieren und Diagrammen in einem beliebigen Tabellenprogramm.',
			'objectives'  => array(
				'Relative und absolute Zellbezüge sicher verwenden',
				'Formeln und einfache Funktionen schreiben',
				'Daten sortieren, filtern und mit Diagrammen auswerten',
			),
			'access'      => array( 'mode' => 'manual' ),
			'exam'        => array(
				'version'    => 'Prüfung 2026',
				'blueprint'  => array( 8, 8, 7, 7 ),
				'time_limit' => 2700,
				'pass'       => array( 3, 4 ),
				'parts'      => array( 3, 5 ),
				'attempts'   => 3,
				'timer_mode' => 'strict',
				'feedback'   => 'after_submit',
			),
			'certificate' => 'Ausstellung bei bestandener Prüfung (nachweisfähig)',
			'modules'     => array(
				array(
					'title'     => 'Zellen und Bezüge',
					'lessons'   => array(
						array( 'l01', 'Zellen, Zeilen, Spalten', 'lesson', '<p>Eine Tabelle besteht aus Spalten, die mit Buchstaben bezeichnet sind, und Zeilen, die nummeriert sind. Die Zelladresse B3 meint Spalte B, Zeile 3. Der Bereich A1:C4 umfasst 3 Spalten mal 4 Zeilen, also 12 Zellen.</p><p>Formeln beginnen immer mit einem Gleichheitszeichen. Text steht standardmäßig linksbündig, Zahlen stehen rechtsbündig.</p>' ),
						array( 'l02', 'Relative und absolute Bezüge', 'video_internal', '<p>Ein relativer Bezug wie A1 passt sich beim Kopieren einer Formel an. Ein absoluter Bezug wie $A$1 bleibt beim Kopieren gleich. Bei gemischten Bezügen ist nur ein Teil fest: Bei $A1 bleibt die Spalte fest, bei A$1 die Zeile.</p><p>In vielen Programmen wechselt die Taste F4 zwischen den Bezugsarten. Die Reihenfolge beim Erstellen einer Formel ist: Zelle auswählen, Gleichheitszeichen eingeben, Formel schreiben, mit der Eingabetaste bestätigen.</p>' ),
						array( 'l03', 'Bezüge üben', 'quiz', '<p>Prüfen Sie Ihr Wissen zu Zellen und Bezügen mit drei kurzen Fragen.</p>' ),
					),
					'questions' => array(
						array(
							's',
							'Was bedeutet die Zelladresse B3?',
							'Spalte B, Zeile 3',
							array( 'Zeile B, Spalte 3', 'Die dritte Tabelle', 'Der Bereich B bis 3' ),
							'inline' => true,
						),
						array(
							's',
							'Womit beginnt jede Formel?',
							'Mit einem Gleichheitszeichen',
							array( 'Mit einem Pluszeichen', 'Mit einer Klammer', 'Mit einem Anführungszeichen' ),
							'inline' => true,
						),
						array(
							's',
							'Welcher Bezug bleibt beim Kopieren vollständig gleich?',
							'$A$1',
							array( 'A1', '$A1', 'A$1' ),
							'inline' => true,
						),
						array( 's', 'Bei welchem Bezug bleibt nur die Spalte fest?', '$A1', array( 'A1', 'A$1', '$A$1' ) ),
						array(
							's',
							'Das Bild zeigt einen Tabellenausschnitt mit markierter Zelle. Welche Adresse hat die markierte Zelle?',
							'C2',
							array( 'B3', '2C', 'A1' ),
							'img' => 'rk2-zelle',
						),
						array( 'm', 'Welche Aussagen zur Ausrichtung stimmen standardmäßig? (mehrere richtig)', array( 'Text steht linksbündig.', 'Zahlen stehen rechtsbündig.' ), array( 'Zahlen stehen linksbündig.', 'Text steht zentriert.' ) ),
						array( 'm', 'Welche Bezüge enthalten mindestens einen festen Teil? (mehrere richtig)', array( '$A$1', '$A1', 'A$1' ), array( 'A1', 'B7' ) ),
						array( 'm', 'Welche Aussagen zur Taste F4 stimmen laut Lektion? (mehrere richtig)', array( 'Sie wechselt zwischen den Bezugsarten.', 'Sie funktioniert in vielen Programmen.' ), array( 'Sie löscht die Formel.', 'Sie speichert die Datei.' ) ),
						array( 'tf', 'Ein relativer Bezug passt sich beim Kopieren einer Formel an.', true ),
						array( 'n', 'Wie viele Zellen umfasst der Bereich A1:C4?', '12', 'absolute', '0', 'Zellen' ),
						array(
							'ma',
							'Ordnen Sie jedem Bezug seine Eigenschaft zu.',
							array(
								'A1'   => 'passt sich vollständig an',
								'$A$1' => 'bleibt vollständig gleich',
								'$A1'  => 'Spalte bleibt fest',
								'A$1'  => 'Zeile bleibt fest',
							),
						),
						array( 'or', 'Bringen Sie die Schritte zum Erstellen einer Formel in die richtige Reihenfolge.', array( 'Zelle auswählen', 'Gleichheitszeichen eingeben', 'Formel schreiben', 'Mit der Eingabetaste bestätigen' ) ),
						array(
							'cl',
							'Ein absoluter Bezug wird mit dem Zeichen {{g1}} geschrieben, zum Beispiel {{g2}}.',
							array(
								'g1' => array( '$', 'Dollarzeichen' ),
								'g2' => array( '$A$1' ),
							),
						),
					),
				),
				array(
					'title'     => 'Formeln und Funktionen',
					'lessons'   => array(
						array( 'l04', 'Grundrechenarten', 'lesson', '<p>Die Rechenzeichen sind + (plus), - (minus), * (mal) und / (geteilt). Es gilt Punkt- vor Strichrechnung: =2+3*4 ergibt 14. Mit Klammern ändert sich die Reihenfolge: =(2+3)*4 ergibt 20.</p><p>Prozent rechnet man zum Beispiel mit =B2*19%. Ist B2 gleich 200, ergibt das 38.</p>' ),
						array( 'l05', 'SUMME, MITTELWERT, MIN und MAX', 'video_internal', '<p>=SUMME(A1:A5) addiert alle Zahlen im Bereich. =MITTELWERT(A1:A5) berechnet den Durchschnitt; leere Zellen werden dabei nicht mitgezählt. =MIN und =MAX liefern den kleinsten und den größten Wert. =ANZAHL zählt die Zellen, die Zahlen enthalten.</p><p>Beispiel: Für die Werte 4, 8 und 12 ist die Summe 24 und der Mittelwert 8.</p>' ),
						array( 'l06', 'Die WENN-Funktion', 'lesson', '<p>Die WENN-Funktion prüft eine Bedingung: =WENN(Bedingung; Dann-Wert; Sonst-Wert). In der deutschen Fassung trennt ein Semikolon die Teile.</p><p>Beispiel: =WENN(B2>=50;"bestanden";"nicht bestanden") gibt „bestanden“ aus, wenn B2 mindestens 50 ist. Das Zeichen >= bedeutet „größer oder gleich“.</p>' ),
					),
					'questions' => array(
						array( 's', 'Was ergibt =2+3*4?', '14', array( '20', '24', '9' ) ),
						array( 's', 'Was ergibt =(2+3)*4?', '20', array( '14', '9', '24' ) ),
						array( 's', 'Welche Funktion berechnet den Durchschnitt?', 'MITTELWERT', array( 'SUMME', 'ANZAHL', 'MAX' ) ),
						array( 's', 'Was zählt die Funktion ANZAHL?', 'Zellen, die Zahlen enthalten', array( 'Alle Zellen eines Bereichs', 'Leere Zellen', 'Zellen mit Text' ) ),
						array( 's', 'Welches Zeichen trennt in der deutschen Fassung die Teile der WENN-Funktion?', 'Semikolon', array( 'Komma', 'Doppelpunkt', 'Schrägstrich' ) ),
						array( 's', 'Was bedeutet das Zeichen >=?', 'größer oder gleich', array( 'kleiner oder gleich', 'ungleich', 'genau gleich' ) ),
						array( 'm', 'Welche Zeichen sind Rechenzeichen? (mehrere richtig)', array( '*', '/', '+' ), array( '$', '#' ) ),
						array( 'm', 'Welche Funktionen liefern einen einzelnen Wert aus einem Bereich? (mehrere richtig)', array( 'MIN', 'MAX', 'MITTELWERT' ), array( 'WENN', 'F4' ) ),
						array( 'm', 'Was gibt =WENN(B2>=50;"bestanden";"nicht bestanden") aus? (mehrere richtig)', array( 'Bei B2 = 50 „bestanden“', 'Bei B2 = 49 „nicht bestanden“' ), array( 'Bei B2 = 49 „bestanden“', 'Bei B2 = 80 „nicht bestanden“' ) ),
						array( 'tf', 'MITTELWERT zählt leere Zellen als Null mit.', false ),
						array( 'o', 'Erklären Sie, wann Klammern in einer Formel nötig sind.', 'Wenn eine Strichrechnung vor einer Punktrechnung ausgeführt werden soll, zum Beispiel =(2+3)*4.' ),
						array( 'n', 'B2 enthält 200. Was ergibt =B2*19% ?', '38', 'absolute', '0', '' ),
						array( 'cl', 'Die Formel ={{g1}}(A1:A5) addiert alle Zahlen im Bereich A1 bis A5.', array( 'g1' => array( 'SUMME' ) ) ),
					),
				),
				array(
					'title'     => 'Daten ordnen',
					'lessons'   => array(
						array( 'l07', 'Sortieren und Filtern', 'lesson', '<p>Aufsteigend sortieren heißt: von A nach Z bzw. von klein nach groß. Markieren Sie vor dem Sortieren den ganzen Bereich mit der Kopfzeile, sonst verrutschen die Zeilen. Für eine zweite Ordnung gibt es weitere Sortierebenen.</p><p>Ein Filter blendet Zeilen nur aus; er löscht keine Daten. Die Reihenfolge beim Filtern: Kopfzeile markieren, Filter einschalten, Wert auswählen, Ergebnis prüfen.</p>' ),
						array( 'l08', 'Übungsdatei', 'file', '<p>Die Übungsdatei (PDF) enthält eine Tabelle mit den Umsätzen eines erfundenen Geschäfts für 12 Monate. Der Umsatz im Januar beträgt 1.200 Euro, im Februar 1.500 Euro und im März 1.800 Euro. Der höchste Monatsumsatz liegt im Dezember bei 3.000 Euro.</p>' ),
					),
					'questions' => array(
						array( 's', 'Was bedeutet „aufsteigend sortieren“ bei Zahlen?', 'Von klein nach groß', array( 'Von groß nach klein', 'Nach Farbe', 'Zufällig' ) ),
						array( 's', 'Was müssen Sie vor dem Sortieren markieren?', 'Den ganzen Bereich mit der Kopfzeile', array( 'Nur die erste Spalte', 'Nur eine Zelle', 'Nichts' ) ),
						array( 's', 'Was macht ein Filter mit nicht passenden Zeilen?', 'Er blendet sie aus.', array( 'Er löscht sie.', 'Er färbt sie rot.', 'Er verschiebt sie ans Ende.' ) ),
						array( 's', 'Wie viele Monate umfasst die Übungsdatei?', '12', array( '3', '6', '24' ) ),
						array(
							's',
							'Das Bild zeigt eine sortierte Liste. Wie ist sie sortiert?',
							'Aufsteigend nach Betrag',
							array( 'Absteigend nach Betrag', 'Nach Monatsnamen', 'Gar nicht' ),
							'img' => 'rk2-sortiert',
						),
						array( 'm', 'Welche Aussagen zum Filter stimmen? (mehrere richtig)', array( 'Er löscht keine Daten.', 'Er blendet Zeilen aus.' ), array( 'Er sortiert immer absteigend.', 'Er entfernt die Kopfzeile.' ) ),
						array( 'm', 'Welche Monatsumsätze stehen in der Übungsdatei? (mehrere richtig)', array( 'Januar 1.200 Euro', 'März 1.800 Euro' ), array( 'Januar 3.000 Euro', 'Februar 1.200 Euro' ) ),
						array( 'm', 'Was kann passieren, wenn man nur eine Spalte sortiert? (mehrere richtig)', array( 'Zeilen verrutschen.', 'Werte passen nicht mehr zusammen.' ), array( 'Die Datei wird kleiner.', 'Nichts.' ) ),
						array( 'tf', 'Für eine zweite Ordnung gibt es weitere Sortierebenen.', true ),
						array( 'n', 'Wie hoch ist laut Übungsdatei der Umsatz im Februar (in Euro)?', '1500', 'absolute', '0', 'Euro' ),
						array(
							'ma',
							'Ordnen Sie den Monaten den Umsatz aus der Übungsdatei zu.',
							array(
								'Januar'   => '1.200 Euro',
								'Februar'  => '1.500 Euro',
								'März'     => '1.800 Euro',
								'Dezember' => '3.000 Euro',
							),
						),
						array( 'or', 'Bringen Sie die Schritte beim Filtern in die richtige Reihenfolge.', array( 'Kopfzeile markieren', 'Filter einschalten', 'Wert auswählen', 'Ergebnis prüfen' ) ),
					),
				),
				array(
					'title'     => 'Auswerten',
					'lessons'   => array(
						array( 'l09', 'Diagramme wählen', 'lesson', '<p>Säulendiagramme eignen sich für Vergleiche zwischen Kategorien, Liniendiagramme für Entwicklungen über die Zeit und Kreisdiagramme für Anteile an einem Ganzen – am besten mit höchstens sechs Teilen.</p><p>Jedes Diagramm braucht einen Titel und beschriftete Achsen. Für Menschen, die das Diagramm nicht sehen, gehört eine kurze Textbeschreibung dazu.</p>' ),
						array( 'l10', 'Aufgabe: Umsatzauswertung', 'assignment', '<p>In dieser Aufgabe werten Sie die Übungsdatei selbst aus.</p>' ),
					),
					'questions' => array(
						array( 's', 'Welches Diagramm eignet sich für eine Entwicklung über die Zeit?', 'Liniendiagramm', array( 'Kreisdiagramm', 'Keines', 'Tabelle' ) ),
						array( 's', 'Welches Diagramm zeigt Anteile an einem Ganzen?', 'Kreisdiagramm', array( 'Liniendiagramm', 'Säulendiagramm', 'Streudiagramm' ) ),
						array( 's', 'Wie viele Teile sollte ein Kreisdiagramm höchstens haben?', 'sechs', array( 'zwei', 'zwölf', 'zwanzig' ) ),
						array( 's', 'Welches Diagramm eignet sich für den Vergleich von Kategorien?', 'Säulendiagramm', array( 'Kreisdiagramm', 'Liniendiagramm', 'Keines' ) ),
						array( 's', 'Was gehört für Menschen, die ein Diagramm nicht sehen, dazu?', 'Eine kurze Textbeschreibung', array( 'Mehr Farben', 'Ein größeres Bild', 'Nichts' ) ),
						array(
							's',
							'Das Säulendiagramm zeigt die Umsätze Januar 1.200, Februar 1.500, März 1.800 Euro. In welchem Monat war der Umsatz am höchsten?',
							'März',
							array( 'Januar', 'Februar', 'Alle gleich' ),
							'img' => 'rk2-diagramm',
						),
						array( 'm', 'Was braucht jedes Diagramm? (mehrere richtig)', array( 'Einen Titel', 'Beschriftete Achsen' ), array( 'Eine Animation', 'Mindestens zehn Farben' ) ),
						array( 'm', 'Für welche Fragen eignet sich ein Liniendiagramm? (mehrere richtig)', array( 'Wie hat sich der Umsatz über das Jahr entwickelt?', 'Wie veränderte sich die Temperatur pro Stunde?' ), array( 'Welchen Anteil hat jede Abteilung am Budget?', 'Wie heißt die Kundin?' ) ),
						array( 'm', 'Welche Diagrammarten nennt die Lektion? (mehrere richtig)', array( 'Säulendiagramm', 'Liniendiagramm', 'Kreisdiagramm' ), array( 'Netzdiagramm', 'Blasendiagramm' ) ),
						array( 'tf', 'Ein Kreisdiagramm eignet sich gut für eine Entwicklung über zwölf Monate.', false ),
						array( 'o', 'Beschreiben Sie, wie Sie die Umsätze von Januar bis Dezember darstellen würden.', 'Als Liniendiagramm oder Säulendiagramm mit Titel, beschrifteten Achsen und einer kurzen Textbeschreibung.' ),
						array( 'n', 'Für die Werte 4, 8 und 12: Wie lautet der Mittelwert?', '8', 'absolute', '0', '' ),
					),
				),
			),
			'assignment'  => array(
				'lesson'          => 'l10',
				'submission_type' => 'file',
				'max_points'      => 20,
				'passing_points'  => 12,
				'completion'      => 'passed',
				'instructions'    => 'Erstellen Sie aus der Übungsdatei ein Diagramm der Monatsumsätze, berechnen Sie Summe und Mittelwert und laden Sie das Ergebnis als PDF hoch.',
				'rubric_text'     => 'Diagrammart passend 5, Titel und Achsen 5, Summe richtig 5, Mittelwert richtig 5.',
				'file_rules'      => array(
					'allowlist' => array( 'pdf' ),
					'max_files' => 1,
				),
			),
			'images'      => array(
				'rk2-zelle'    => array(
					'kind' => 'grid',
					'alt'  => 'Tabellenausschnitt mit den Spalten A bis D und den Zeilen 1 bis 4; die Zelle in Spalte C, Zeile 2 ist markiert.',
				),
				'rk2-sortiert' => array(
					'kind' => 'list',
					'alt'  => 'Liste mit vier Beträgen von oben nach unten: 120, 340, 560, 910.',
				),
				'rk2-diagramm' => array(
					'kind' => 'bars',
					'alt'  => 'Säulendiagramm Monatsumsatz: Januar 1.200 Euro, Februar 1.500 Euro, März 1.800 Euro.',
				),
			),
		),

		// ------------------------------------------------------------------
		// RK-3 Onboarding.
		// ------------------------------------------------------------------
		'rk3' => array(
			'id'          => 'RK-3',
			'slug'        => 'cc-fx-rk3',
			'title'       => 'Einarbeitung bei der Beispiel GmbH',
			'summary'     => 'Onboarding-Kurs eines erfundenen Unternehmens: Arbeitszeit, IT, Datenschutz, Sicherheit und Ansprechpersonen.',
			'objectives'  => array(
				'Regeln zu Arbeitszeit, Pausen und Urlaub kennen',
				'Sicher mit Passwörtern und Daten umgehen',
				'Im Notfall richtig handeln',
			),
			'access'      => array(
				'mode'      => 'group',
				'seats'     => 5,
				'drip_days' => 1,
			),
			'exam'        => array(
				'version'    => 'Abschlusscheck',
				'fixed'      => array( 8, 7 ),
				'time_limit' => 1200,
				'pass'       => array( 4, 5 ),
				'parts'      => null,
				'attempts'   => null,
				'timer_mode' => 'practice',
				'feedback'   => 'after_submit',
			),
			'certificate' => 'Ausstellung bei bestandenem Abschlusscheck',
			'modules'     => array(
				array(
					'title'     => 'Ankommen',
					'lessons'   => array(
						array( 'l01', 'Willkommen', 'lesson_confirm', '<p>Die Beispiel GmbH ist ein erfundenes Unternehmen. Sie wurde 2010 gegründet, hat 120 Mitarbeitende und drei Abteilungen: Vertrieb, Produktion und Verwaltung. Das Leitbild lautet „Gemeinsam, verlässlich, offen“.</p><p>Am ersten Tag werden Sie um 9 Uhr am Empfang im Erdgeschoss abgeholt. Eine Patin oder ein Pate begleitet Sie in den ersten zwei Wochen.</p>' ),
						array( 'l02', 'Arbeitszeit und Pausen', 'lesson_confirm', '<p>Es gilt Gleitzeit mit einer Kernzeit von 9:30 bis 15:00 Uhr. Die Arbeitszeit erfassen Sie am Terminal am Eingang. Ab 6 Stunden Arbeit stehen Ihnen 30 Minuten Pause zu, ab 9 Stunden 45 Minuten.</p><p>Überstunden sprechen Sie vorher mit Ihrer Führungskraft ab. Urlaub beantragen Sie im Personalportal; Sie haben 30 Urlaubstage im Jahr.</p>' ),
						array( 'l03', 'Rundgang durch das Haus', 'video_internal', '<p>Die Kantine liegt im ersten Obergeschoss und ist von 11:30 bis 14:00 Uhr geöffnet. Der Erste-Hilfe-Raum ist im Erdgeschoss neben dem Empfang. Der Sammelplatz im Notfall ist der Parkplatz Nord. Besprechungsräume buchen Sie über den Kalender.</p>' ),
						array( 'l04', 'Telefonliste', 'file', '<p>Die Telefonliste (PDF) enthält die internen Durchwahlen: Empfang 100, IT-Service 200, Personal 300 und Werkschutz 999. Bei Lebensgefahr wählen Sie immer zuerst den Notruf 112.</p>' ),
						array( 'l05', 'IT-Zugang und Passwörter', 'lesson_confirm', '<p>Ihr Passwort hat mindestens 12 Zeichen und wird niemals weitergegeben. Der IT-Service fragt nie nach Ihrem Passwort. Wenn Sie den Platz verlassen, sperren Sie den Bildschirm.</p><p>Verdächtige E-Mails melden Sie dem IT-Service. Software wird nur über den IT-Service installiert.</p>' ),
					),
					'questions' => array(
						array( 's', 'Wann wurde die Beispiel GmbH gegründet?', '2010', array( '1990', '2000', '2020' ) ),
						array( 's', 'Wie viele Mitarbeitende hat die Beispiel GmbH?', '120', array( '12', '50', '1.200' ) ),
						array( 's', 'Wo werden Sie am ersten Tag abgeholt?', 'Am Empfang im Erdgeschoss', array( 'In der Kantine', 'Am Parkplatz Nord', 'Im Besprechungsraum' ) ),
						array( 's', 'Wie lange begleitet Sie die Patin oder der Pate?', 'Zwei Wochen', array( 'Einen Tag', 'Sechs Monate', 'Ein Jahr' ) ),
						array( 's', 'Wann ist die Kernzeit?', '9:30 bis 15:00 Uhr', array( '8:00 bis 12:00 Uhr', '7:00 bis 16:00 Uhr', 'Es gibt keine Kernzeit.' ) ),
						array( 's', 'Wie viel Pause steht Ihnen ab 6 Stunden Arbeit zu?', '30 Minuten', array( '15 Minuten', '45 Minuten', '60 Minuten' ) ),
						array( 's', 'Wo beantragen Sie Urlaub?', 'Im Personalportal', array( 'Am Empfang', 'Per Zettel in der Kantine', 'Beim IT-Service' ) ),
						array( 's', 'Wo liegt die Kantine?', 'Im ersten Obergeschoss', array( 'Im Erdgeschoss', 'Im Keller', 'Im Nebengebäude' ) ),
						array(
							's',
							'Das Bild zeigt den Lageplan. Wo ist der Sammelplatz im Notfall?',
							'Parkplatz Nord',
							array( 'Parkplatz Süd', 'Kantine', 'Empfang' ),
							'img' => 'rk3-lageplan',
						),
						array( 's', 'Welche Durchwahl hat der IT-Service?', '200', array( '100', '300', '999' ) ),
						array( 's', 'Wie viele Zeichen hat ein Passwort mindestens?', '12', array( '4', '6', '8' ) ),
						array( 's', 'Was tun Sie, wenn Sie Ihren Platz verlassen?', 'Den Bildschirm sperren', array( 'Den Computer laufen lassen', 'Das Passwort auf einen Zettel schreiben', 'Den Monitor ausschalten' ) ),
						array( 's', 'Wer installiert Software auf Ihrem Rechner?', 'Der IT-Service', array( 'Sie selbst', 'Die Patin oder der Pate', 'Der Empfang' ) ),
						array( 'm', 'Welche Abteilungen hat die Beispiel GmbH? (mehrere richtig)', array( 'Vertrieb', 'Produktion', 'Verwaltung' ), array( 'Forschung', 'Marketing' ) ),
						array( 'm', 'Welche Begriffe gehören zum Leitbild? (mehrere richtig)', array( 'gemeinsam', 'verlässlich', 'offen' ), array( 'schnell', 'billig' ) ),
						array( 'm', 'Welche Regeln zur Pause stimmen? (mehrere richtig)', array( 'Ab 6 Stunden 30 Minuten', 'Ab 9 Stunden 45 Minuten' ), array( 'Ab 4 Stunden 60 Minuten', 'Pausen sind freiwillig.' ) ),
						array( 'm', 'Welche Durchwahlen stehen in der Telefonliste? (mehrere richtig)', array( 'Empfang 100', 'Personal 300' ), array( 'IT-Service 100', 'Werkschutz 112' ) ),
						array( 'm', 'Was gilt für Passwörter? (mehrere richtig)', array( 'Niemals weitergeben', 'Mindestens 12 Zeichen' ), array( 'Dem IT-Service auf Nachfrage nennen', 'Auf dem Monitor notieren' ) ),
						array( 'tf', 'Überstunden sprechen Sie vorher mit Ihrer Führungskraft ab.', true ),
						array( 'tf', 'Der IT-Service fragt manchmal nach Ihrem Passwort.', false ),
						array( 'tf', 'Sie haben 30 Urlaubstage im Jahr.', true ),
						array( 'tf', 'Die Kantine ist bis 16 Uhr geöffnet.', false ),
						array( 'tf', 'Bei Lebensgefahr wählen Sie zuerst den Notruf 112.', true ),
						array( 'f', 'Kernzeit', '9:30 bis 15:00 Uhr' ),
						array( 'f', 'Durchwahl IT-Service', '200' ),
					),
				),
				array(
					'title'     => 'Zusammenarbeiten',
					'lessons'   => array(
						array( 'l06', 'Datenschutz im Alltag', 'lesson_confirm', '<p>Personenbezogene Daten verwenden Sie nur für den Zweck, für den sie erhoben wurden. Unterlagen lassen Sie nicht offen liegen („aufgeräumter Schreibtisch“). Papier mit Personendaten gehört in die Datenschutztonne.</p><p>Eine Datenpanne, zum Beispiel eine falsch versendete E-Mail, melden Sie sofort an datenschutz@example.test – spätestens innerhalb von 24 Stunden.</p>' ),
						array( 'l07', 'Brandschutz', 'video_external', '<p>Feuerlöscher hängen an jedem Treppenhaus. Bei Alarm verlassen Sie das Gebäude über den Fluchtweg und benutzen keine Aufzüge. Sammelplatz ist der Parkplatz Nord. Brandschutzhelferinnen und -helfer tragen gelbe Westen. Der Notruf der Feuerwehr ist 112.</p>' ),
						array( 'l08', 'Notfallplan', 'file', '<p>Der Notfallplan (PDF) hängt in jedem Flur. Auf jeder Etage gibt es mindestens zwei Ersthelferinnen oder Ersthelfer. Verbandkästen stehen in den Teeküchen.</p>' ),
						array( 'l09', 'Ansprechpersonen', 'lesson_confirm', '<p>Fragen zum Gehalt beantwortet die Personalabteilung. Bei Technikproblemen hilft der IT-Service (Durchwahl 200). Für die fachliche Einarbeitung ist Ihre Patin oder Ihr Pate da. Der Betriebsrat hat dienstags von 14 bis 15 Uhr Sprechstunde.</p><p>Nach drei Monaten gibt es ein Feedbackgespräch; die Probezeit endet nach sechs Monaten.</p>' ),
						array( 'l10', 'Aufgabe: Mein Einarbeitungsplan', 'assignment', '<p>In dieser Aufgabe halten Sie fest, wie Ihre ersten Wochen laufen.</p>' ),
					),
					'questions' => array(
						array( 's', 'Wohin gehört Papier mit Personendaten?', 'In die Datenschutztonne', array( 'In den Papierkorb', 'In die Ablage am Empfang', 'In den Restmüll' ) ),
						array( 's', 'Wann melden Sie eine Datenpanne spätestens?', 'Innerhalb von 24 Stunden', array( 'Innerhalb einer Woche', 'Am Monatsende', 'Gar nicht' ) ),
						array( 's', 'An welche Adresse melden Sie eine Datenpanne?', 'datenschutz@example.test', array( 'it@example.test', 'empfang@example.test', 'chef@example.test' ) ),
						array( 's', 'Wo hängen die Feuerlöscher?', 'An jedem Treppenhaus', array( 'Nur in der Kantine', 'Im Keller', 'Am Parkplatz' ) ),
						array( 's', 'Woran erkennen Sie Brandschutzhelferinnen und -helfer?', 'An gelben Westen', array( 'An roten Mützen', 'An einem Namensschild', 'Gar nicht' ) ),
						array( 's', 'Wo hängt der Notfallplan?', 'In jedem Flur', array( 'Nur am Empfang', 'Im Personalportal', 'Im Büro der Geschäftsführung' ) ),
						array( 's', 'Wo stehen die Verbandkästen?', 'In den Teeküchen', array( 'Am Parkplatz Nord', 'In der Kantine', 'Im Serverraum' ) ),
						array( 's', 'Wer beantwortet Fragen zum Gehalt?', 'Die Personalabteilung', array( 'Der IT-Service', 'Der Empfang', 'Die Patin oder der Pate' ) ),
						array( 's', 'Wann hat der Betriebsrat Sprechstunde?', 'Dienstags von 14 bis 15 Uhr', array( 'Montags von 9 bis 10 Uhr', 'Freitags von 12 bis 13 Uhr', 'Täglich' ) ),
						array( 's', 'Wann gibt es das Feedbackgespräch?', 'Nach drei Monaten', array( 'Nach einer Woche', 'Nach einem Jahr', 'Nie' ) ),
						array( 's', 'Wann endet die Probezeit?', 'Nach sechs Monaten', array( 'Nach drei Monaten', 'Nach einem Monat', 'Nach zwei Jahren' ) ),
						array( 's', 'Wer hilft bei der fachlichen Einarbeitung?', 'Die Patin oder der Pate', array( 'Der Betriebsrat', 'Der Werkschutz', 'Die Kantine' ) ),
						array( 'm', 'Was tun Sie bei Feueralarm? (mehrere richtig)', array( 'Das Gebäude über den Fluchtweg verlassen', 'Zum Parkplatz Nord gehen' ), array( 'Den Aufzug benutzen', 'Am Platz weiterarbeiten' ) ),
						array( 'm', 'Was ist eine Datenpanne? (mehrere richtig)', array( 'Eine falsch versendete E-Mail mit Personendaten', 'Verlorene Unterlagen mit Personendaten' ), array( 'Ein leerer Drucker', 'Eine Besprechung' ) ),
						array( 'm', 'Was gehört zum Datenschutz im Alltag? (mehrere richtig)', array( 'Daten nur für ihren Zweck nutzen', 'Unterlagen nicht offen liegen lassen' ), array( 'Personendaten an Freunde schicken', 'Passwörter teilen' ) ),
						array( 'm', 'Was steht im Notfallplan? (mehrere richtig)', array( 'Ersthelferinnen und Ersthelfer je Etage', 'Wo die Verbandkästen stehen' ), array( 'Der Speiseplan', 'Die Urlaubstage' ) ),
						array( 'm', 'Wer ist für welche Frage zuständig? (mehrere richtig)', array( 'IT-Service bei Technikproblemen', 'Personalabteilung bei Gehaltsfragen' ), array( 'Betriebsrat bei Druckerproblemen', 'Empfang bei Gehaltsfragen' ) ),
						array( 'tf', 'Bei Feueralarm benutzen Sie den Aufzug.', false ),
						array( 'tf', 'Auf jeder Etage gibt es mindestens zwei Ersthelferinnen oder Ersthelfer.', true ),
						array( 'tf', 'Der Notruf der Feuerwehr ist 112.', true ),
						array( 'tf', 'Die Probezeit endet nach drei Monaten.', false ),
						array( 'tf', 'Personendaten dürfen Sie für jeden beliebigen Zweck verwenden.', false ),
						array( 'f', 'Sammelplatz', 'Parkplatz Nord' ),
						array( 'f', 'Datenpanne melden', 'sofort, spätestens innerhalb von 24 Stunden, an datenschutz@example.test' ),
						array( 'f', 'Sprechstunde Betriebsrat', 'dienstags 14 bis 15 Uhr' ),
					),
				),
			),
			'assignment'  => array(
				'lesson'          => 'l10',
				'submission_type' => 'text_and_file',
				'max_points'      => 10,
				'passing_points'  => null,
				'completion'      => 'submitted',
				'instructions'    => 'Beschreiben Sie in wenigen Sätzen, welche Aufgaben Sie in den ersten Wochen übernehmen und welche Frage Sie an Ihr Team haben. Laden Sie zusätzlich Ihren ausgefüllten Einarbeitungsplan als PDF hoch.',
				'rubric_text'     => 'Vollständigkeit 5, Bezug zu den Kursinhalten 5.',
				'file_rules'      => array(
					'allowlist' => array( 'pdf', 'docx', 'odt' ),
					'max_files' => 2,
				),
			),
			'images'      => array(
				'rk3-lageplan' => array(
					'kind' => 'map',
					'alt'  => 'Lageplan des Firmengeländes: Hauptgebäude in der Mitte, Parkplatz Nord oben mit Markierung „Sammelplatz“, Parkplatz Süd unten.',
				),
			),
		),
	),
);
