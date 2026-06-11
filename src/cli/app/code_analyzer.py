import re
from app.models import AnalysisResult

EXCLUDED_FILE_PATTERNS = re.compile(
    r"^diff --git a/(.*\.lock|package-lock\.json|yarn\.lock|.*\.min\.js|.*\.min\.css)\b",
    re.MULTILINE,
)

class CodeAnalyzer:
    MAX_WORDS = int(8000 / 1.3)  # ≈ 6153 words ≈ 8000 tokens

    def analyze(self, commits: list, diff: str) -> AnalysisResult:
        cleaned = self._strip_excluded(diff)
        cleaned = self._strip_binary(cleaned)
        cleaned = self._truncate(cleaned)
        messages = [c.message.strip() for c in commits]
        return AnalysisResult(diff=cleaned, commit_messages=messages)

    def _strip_excluded(self, diff: str) -> str:
        # Split on "diff --git" boundaries and discard excluded files
        hunks = re.split(r"(?=^diff --git )", diff, flags=re.MULTILINE)
        kept = []
        for hunk in hunks:
            if not hunk:
                continue
            if EXCLUDED_FILE_PATTERNS.match(hunk):
                continue
            kept.append(hunk)
        return "".join(kept)

    def _strip_binary(self, diff: str) -> str:
        hunks = re.split(r"(?=^diff --git )", diff, flags=re.MULTILINE)
        kept = []
        for hunk in hunks:
            if not hunk:
                continue
            if "Binary files" in hunk:
                continue
            kept.append(hunk)
        return "".join(kept)

    def _truncate(self, diff: str) -> str:
        words = diff.split()
        if len(words) <= self.MAX_WORDS:
            return diff
        return " ".join(words[: self.MAX_WORDS]) + "\n...[truncated]"
