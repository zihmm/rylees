from typing import Literal
import git
from app.models import AnalysisResult

class GitConnectorError(Exception):
    pass

class GitConnector:
    def __init__(self, repo_path: str = "."):
        try:
            self._repo = git.Repo(repo_path)
        except git.InvalidGitRepositoryError:
            raise GitConnectorError(f"Not a valid git repository: {repo_path}")

    def current_branch(self) -> str:
        """Return the active branch name, or the short HEAD sha when detached."""
        try:
            return self._repo.active_branch.name
        except TypeError:
            return self._repo.head.commit.hexsha[:7]

    def get_diff(
        self,
        start_ref: str,
        end_ref: str,
        ref_type: Literal["tag", "commit"],
    ) -> tuple[list[git.Commit], str]:
        try:
            if ref_type == "tag":
                start = self._repo.tags[start_ref].commit
                end = self._repo.tags[end_ref].commit if end_ref != "HEAD" else self._repo.head.commit
            else:
                start = self._repo.commit(start_ref)
                end = self._repo.commit(end_ref) if end_ref != "HEAD" else self._repo.head.commit
        except (IndexError, git.BadName, KeyError, ValueError):
            raise GitConnectorError(f"Reference not found: check --start and --end values")

        commits = list(self._repo.iter_commits(f"{start.hexsha}..{end.hexsha}"))
        diff_str = self._repo.git.diff(start.hexsha, end.hexsha)
        return commits, diff_str
