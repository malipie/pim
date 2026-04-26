// PIM — Conventional Commits enforcement.
// CLAUDE.md "Konwencje języka i commit messages" — types: feat, fix, chore, docs, refactor,
// test, ci, build, perf, style. Subject ≤72 chars, imperative mood, no trailing period.
// Body explains *why* (not *what*). No `Co-Authored-By` for AI tools — git history is
// neutral about generation tooling.

/** @type {import('@commitlint/types').UserConfig} */
module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [
      2,
      'always',
      ['feat', 'fix', 'chore', 'docs', 'refactor', 'test', 'ci', 'build', 'perf', 'style'],
    ],
    'subject-max-length': [2, 'always', 72],
    'subject-case': [0],
    'body-max-line-length': [0],
    'footer-max-line-length': [0],
  },
};
