import git
import pytest
from app.git_connector import GitConnector, GitConnectorError


def test_raises_for_invalid_repo(tmp_path):
    with pytest.raises(GitConnectorError):
        GitConnector(str(tmp_path))


@pytest.fixture
def repo(tmp_path):
    r = git.Repo.init(tmp_path)
    with r.config_writer() as cw:
        cw.set_value("user", "name", "Test User")
        cw.set_value("user", "email", "test@example.com")
    f = tmp_path / "a.txt"
    f.write_text("first\n")
    r.index.add(["a.txt"])
    r.index.commit("initial commit")
    return tmp_path


def test_raises_for_unknown_tag(repo):
    gc = GitConnector(str(repo))
    with pytest.raises(GitConnectorError):
        gc.get_diff("v9.9.9", "HEAD", "tag")


def test_raises_for_unknown_commit(repo):
    gc = GitConnector(str(repo))
    with pytest.raises(GitConnectorError):
        gc.get_diff("deadbeefdeadbeefdeadbeefdeadbeefdeadbeef", "HEAD", "commit")
