#!/usr/bin/env python3
"""
Create GitHub Issues for RBAC backlog (Phase 1-7, 89 tickets).

Usage:
    python3 tools/create-rbac-issues.py --setup           # ensure labels + milestones
    python3 tools/create-rbac-issues.py --dry-run-first-3 # create P1-001/002/003 only
    python3 tools/create-rbac-issues.py --bulk            # create all 89 (idempotent)
    python3 tools/create-rbac-issues.py --cross-refs      # second-pass: replace RBAC-PX-NNN with #N
    python3 tools/create-rbac-issues.py --summary         # parse only, print summary

Idempotent: tools/rbac-issues-mapping.json tracks RBAC-PX-NNN -> issue_number.
Existing entries skipped on re-run.
"""

import argparse
import json
import os
import re
import subprocess
import sys
import tempfile
import time
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent
MAPPING_FILE = REPO_ROOT / "tools" / "rbac-issues-mapping.json"

PHASE_FILES = {
    1: ("Project Plan/08-rbac-tickets-phase-1.md", "RBAC Phase 1 (Foundation)",
        "Tooling + ADR + schema + seed + bundle skeleton + PHPStan rules"),
    2: ("Project Plan/09-rbac-tickets-phase-2.md", "RBAC Phase 2 (Backend Auth)",
        "JWT + email/password + API tokens + tenant context + RLS + permission resolver + MFA + SSO"),
    3: ("Project Plan/10-rbac-tickets-phase-3.md", "RBAC Phase 3 (Permission Engine)",
        "Voters + #[RequiresPermission] + field-level filtering + workflow policy + Super Admin bypass"),
    4: ("Project Plan/11-rbac-tickets-phase-4.md", "RBAC Phase 4 (Frontend Core)",
        "Session bootstrap + route guards + PermissionGate + interceptors + MFA UI"),
    5: ("Project Plan/12-rbac-tickets-phase-5.md", "RBAC Phase 5 (Settings UI)",
        "Users/Roles UI + role builder + per-attribute grants + API tokens + Super Admin panel"),
    6: ("Project Plan/13-rbac-tickets-phase-6.md", "RBAC Phase 6 (Refactor + Hardening)",
        "Existing endpoints audit + #[RequiresPermission] retrofit + CI gates lockdown + observability"),
    7: ("Project Plan/14-rbac-tickets-phase-7.md", "RBAC Phase 7 (Pentest + Launch)",
        "Red-team + external pentest + critical fixes + user-facing docs + soft launch"),
}

LABELS = [
    ("rbac", "5319E7", "Role-Based Access Control epic — all RBAC tickets across phases"),
    ("phase-1", "0E8A16", "RBAC Phase 1 — Foundation (tooling, ADR, schema, seed, PHPStan rules)"),
    ("phase-2", "1D76DB", "RBAC Phase 2 — Backend auth + tenant context"),
    ("phase-3", "1D76DB", "RBAC Phase 3 — Permission engine (Voters, field-level, workflow)"),
    ("phase-4", "FBCA04", "RBAC Phase 4 — Frontend core (session, guards, PermissionGate)"),
    ("phase-5", "FBCA04", "RBAC Phase 5 — Settings UI (Users/Roles/Tokens/Super Admin)"),
    ("phase-6", "C5A3FF", "RBAC Phase 6 — Refactor existing endpoints + hardening"),
    ("phase-7", "B60205", "RBAC Phase 7 — Pentest + soft launch"),
    ("needs-planning", "FBCA04", "Requires Plan Mode (architecture decision / multi-file impact) before implementation"),
    ("ready-to-implement", "0E8A16", "Plan Mode complete — agent can start coding"),
    ("risk:critical", "B60205", "Critical security risk (cross-tenant leakage, privilege escalation, break-glass)"),
    ("risk:high", "D93F0B", "High security risk (auth, token, permission enforcement, secrets)"),
    ("risk:medium", "CFD3D7", "Medium risk (docs, tooling, UI components without security implications)"),
]

TICKET_HEADER_RE = re.compile(r"^## (RBAC-P\d-\d{3}): (.+)$", re.MULTILINE)
CROSS_REF_RE = re.compile(r"\bRBAC-P\d-\d{3}\b")


def run_gh(args, check=True, capture=True):
    """Run gh CLI command, return stdout string."""
    cmd = ["gh"] + args
    result = subprocess.run(cmd, capture_output=capture, text=True)
    if check and result.returncode != 0:
        sys.stderr.write(f"FAIL: {' '.join(cmd)}\nSTDERR: {result.stderr}\nSTDOUT: {result.stdout}\n")
        sys.exit(1)
    return result.stdout.strip()


def load_mapping():
    if MAPPING_FILE.exists():
        return json.loads(MAPPING_FILE.read_text())
    return {}


def save_mapping(mapping):
    """Atomic write: temp file + rename."""
    MAPPING_FILE.parent.mkdir(parents=True, exist_ok=True)
    fd, tmp_path = tempfile.mkstemp(dir=MAPPING_FILE.parent, prefix=".mapping.", suffix=".tmp")
    try:
        with os.fdopen(fd, "w") as f:
            json.dump(mapping, f, indent=2, sort_keys=True)
            f.write("\n")
        os.replace(tmp_path, MAPPING_FILE)
    except Exception:
        if os.path.exists(tmp_path):
            os.unlink(tmp_path)
        raise


def ensure_labels():
    """Create missing labels."""
    existing = run_gh(["label", "list", "--limit", "200", "--json", "name", "--jq", ".[].name"]).split("\n")
    existing = set(existing)
    for name, color, desc in LABELS:
        if name in existing:
            print(f"  label exists: {name}")
            continue
        run_gh(["label", "create", name, "--color", color, "--description", desc])
        print(f"  label CREATED: {name} (#{color})")
        time.sleep(0.3)


def ensure_milestones():
    """Create missing milestones, return {title: number} map."""
    existing_raw = run_gh(["api", "repos/:owner/:repo/milestones", "--paginate", "--jq", ".[] | {title, number}"])
    existing = {}
    for line in existing_raw.split("\n"):
        if line.strip():
            obj = json.loads(line)
            existing[obj["title"]] = obj["number"]
    for phase_num, (_, title, desc) in PHASE_FILES.items():
        if title in existing:
            print(f"  milestone exists: {title} (#{existing[title]})")
            continue
        out = run_gh(["api", "repos/:owner/:repo/milestones",
                      "-f", f"title={title}",
                      "-f", f"description={desc}"])
        num = json.loads(out)["number"]
        existing[title] = num
        print(f"  milestone CREATED: {title} (#{num})")
        time.sleep(0.3)
    return existing


def parse_phase_file(phase_num):
    """Return list of (code, title, body) tuples."""
    rel_path, _, _ = PHASE_FILES[phase_num]
    content = (REPO_ROOT / rel_path).read_text()
    matches = list(TICKET_HEADER_RE.finditer(content))
    tickets = []
    for i, m in enumerate(matches):
        code = m.group(1)
        title = m.group(2).strip()
        body_start = m.end() + 1  # past trailing newline
        body_end = matches[i + 1].start() if i + 1 < len(matches) else len(content)
        body = content[body_start:body_end]
        # Trim trailing separator lines
        body = re.sub(r"\n+---\s*\n?$", "\n", body)
        body = body.rstrip() + "\n"
        tickets.append((code, title, body))
    return tickets


def classify_risk(body):
    """Map ticket body to risk:critical | risk:high | risk:medium."""
    upper = body.upper()
    # Critical: cross-tenant leakage or explicit CRITICAL flag
    if "CROSS-TENANT LEAKAGE" in upper or "CROSS-TENANT" in upper:
        return "risk:critical"
    # Explicit CRITICAL token (not "critical path" / "critical findings" — those are reporting words, not flags)
    if re.search(r"\bCRITICAL\b", body) and "**Risk flags:**" in body:
        # double-check: appears within or near Risk flags section
        rf_idx = body.find("**Risk flags:**")
        cel_idx = body.find("**Cel")
        if cel_idx == -1:
            cel_idx = len(body)
        section = body[rf_idx:cel_idx]
        if "CRITICAL" in section.upper():
            return "risk:critical"
    # High: has Risk flags section with security wording
    if "**Risk flags:**" in body:
        return "risk:high"
    return "risk:medium"


def create_issue(code, title, body, phase_num, risk_label, milestone_title):
    """Create one GitHub issue. Returns issue number (int)."""
    labels = f"rbac,phase-{phase_num},needs-planning,{risk_label}"
    # Write body to temp file (avoids shell escaping issues with multi-line + special chars)
    fd, tmp_path = tempfile.mkstemp(suffix=".md", prefix=f"rbac-{code}-")
    try:
        with os.fdopen(fd, "w") as f:
            f.write(body)
        url = run_gh([
            "issue", "create",
            "--title", title,
            "--body-file", tmp_path,
            "--label", labels,
            "--milestone", milestone_title,
        ])
    finally:
        os.unlink(tmp_path)
    # url is like https://github.com/malipie/PIM/issues/123
    m = re.search(r"/issues/(\d+)$", url)
    if not m:
        raise RuntimeError(f"Cannot parse issue number from: {url}")
    return int(m.group(1))


def cmd_setup(args):
    print("=== Etap 1: Labels ===")
    ensure_labels()
    print("=== Etap 1: Milestones ===")
    milestones = ensure_milestones()
    print(f"\nDONE. {len(LABELS)} labels + {len(milestones)} milestones ready.")


def cmd_summary(args):
    print("=== Etap 2: Parser summary ===\n")
    total = 0
    risk_counts = {"risk:critical": 0, "risk:high": 0, "risk:medium": 0}
    for phase_num in sorted(PHASE_FILES.keys()):
        tickets = parse_phase_file(phase_num)
        print(f"--- Phase {phase_num} ({len(tickets)} tickets) ---")
        for code, title, body in tickets:
            risk = classify_risk(body)
            risk_counts[risk] += 1
            short_title = (title[:80] + "...") if len(title) > 80 else title
            print(f"  {code} [{risk:15}] {short_title}")
            total += 1
        print()
    print(f"TOTAL: {total} tickets (expected 89)")
    print(f"  critical: {risk_counts['risk:critical']}")
    print(f"  high:     {risk_counts['risk:high']}")
    print(f"  medium:   {risk_counts['risk:medium']}")
    if total != 89:
        print(f"ERROR: count mismatch ({total} != 89)")
        sys.exit(1)


def cmd_create(args, dry_run_first_3=False):
    print("=== Etap 3/4: Create issues ===\n")
    mapping = load_mapping()
    created_count = 0
    skipped_count = 0
    target_codes = None
    if dry_run_first_3:
        target_codes = {"RBAC-P1-001", "RBAC-P1-002", "RBAC-P1-003"}
    for phase_num in sorted(PHASE_FILES.keys()):
        _, milestone_title, _ = PHASE_FILES[phase_num]
        tickets = parse_phase_file(phase_num)
        for code, title, body in tickets:
            if target_codes is not None and code not in target_codes:
                continue
            if code in mapping:
                print(f"  [skip] {code} -> already #{mapping[code]}")
                skipped_count += 1
                continue
            risk = classify_risk(body)
            try:
                issue_num = create_issue(code, title, body, phase_num, risk, milestone_title)
            except Exception as e:
                print(f"FAIL {code}: {e}", file=sys.stderr)
                save_mapping(mapping)  # save what we have so far
                raise
            mapping[code] = issue_num
            save_mapping(mapping)  # atomic save per ticket
            created_count += 1
            print(f"  [{created_count}] {code} -> #{issue_num} ({risk})")
            time.sleep(0.5)
    print(f"\nDONE. Created {created_count}, skipped {skipped_count}.")
    if dry_run_first_3:
        print("\n=== DRY RUN COMPLETE — review URLs in GitHub ===")
        for code in sorted(target_codes):
            if code in mapping:
                url = f"https://github.com/malipie/PIM/issues/{mapping[code]}"
                print(f"  {code}: {url}")
        print("\nSTOP. Re-run with --bulk after operator approves format.")


def cmd_cross_refs(args):
    print("=== Etap 5: Cross-reference replacement ===\n")
    mapping = load_mapping()
    if not mapping:
        print("ERROR: mapping empty. Run --bulk first.", file=sys.stderr)
        sys.exit(1)
    updated_count = 0
    skipped_count = 0
    for phase_num in sorted(PHASE_FILES.keys()):
        tickets = parse_phase_file(phase_num)
        for code, title, body in tickets:
            if code not in mapping:
                print(f"  [skip] {code}: not in mapping")
                skipped_count += 1
                continue
            def replace(m):
                ref = m.group(0)
                if ref in mapping:
                    return f"#{mapping[ref]}"
                return ref  # leave as-is if no matching issue
            new_body = CROSS_REF_RE.sub(replace, body)
            # Also remove self-reference noise: if body starts with `RBAC-PX-NNN /` lines, strip
            if new_body == body:
                print(f"  [no-change] {code} -> #{mapping[code]}")
                skipped_count += 1
                continue
            # Write to temp file + gh issue edit
            fd, tmp_path = tempfile.mkstemp(suffix=".md", prefix=f"rbac-edit-{code}-")
            try:
                with os.fdopen(fd, "w") as f:
                    f.write(new_body)
                run_gh(["issue", "edit", str(mapping[code]), "--body-file", tmp_path])
            finally:
                os.unlink(tmp_path)
            updated_count += 1
            print(f"  [{updated_count}] {code} -> #{mapping[code]} body updated")
            time.sleep(0.5)
    print(f"\nDONE. Updated {updated_count}, unchanged {skipped_count}.")


def main():
    p = argparse.ArgumentParser(description=__doc__)
    g = p.add_mutually_exclusive_group(required=True)
    g.add_argument("--setup", action="store_true", help="Create labels + milestones")
    g.add_argument("--summary", action="store_true", help="Parse + print summary, no API calls")
    g.add_argument("--dry-run-first-3", action="store_true", help="Create only P1-001/002/003")
    g.add_argument("--bulk", action="store_true", help="Create all 89 (idempotent)")
    g.add_argument("--cross-refs", action="store_true", help="Replace RBAC-PX-NNN with #N in all issues")
    args = p.parse_args()
    if args.setup:
        cmd_setup(args)
    elif args.summary:
        cmd_summary(args)
    elif args.dry_run_first_3:
        cmd_create(args, dry_run_first_3=True)
    elif args.bulk:
        cmd_create(args, dry_run_first_3=False)
    elif args.cross_refs:
        cmd_cross_refs(args)


if __name__ == "__main__":
    main()
