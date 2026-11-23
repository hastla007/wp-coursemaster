// Semgrep rule tests for cc-rules.yml (run: semgrep --test --metrics=off tools/semgrep/).

// ruleid: cc-js-innerhtml
el.innerHTML = data.text;
// ok: cc-js-innerhtml
el.textContent = data.text;
// ruleid: cc-js-innerhtml
el.insertAdjacentHTML( 'beforeend', html );

// ruleid: cc-js-eval
eval( code );
// ruleid: cc-js-eval
setTimeout( 'run()', 10 );
// ok: cc-js-eval
setTimeout( run, 10 );
