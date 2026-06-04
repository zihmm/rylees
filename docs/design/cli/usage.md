# Rylees CLI

Rylees is an AI-driven release notes generator. It compares your code changes
between two points in your Git history and writes the release notes for you.

After generating the release notes, you can edit the content and publish it on
the platform in the release history of the corresponding customer project.

## Synopsis

```text
rylees <command> [options]
```

## Commands

| Command    | Alias | Description                         |
| :--------- | :---- | :---------------------------------- |
| `generate` | `gen` | Generate release notes from commits |
| `help`     | -     | Display this help                   |

## Global options

Available on every command.

| Short | Long      | Value | Description                | Default |
| :---- | :-------- | :---- | :------------------------- | :------ |
| -V    | --version | —     | Show the installed version | —       |

## Generate

Generate release notes from the commits between a start and an end reference.

```bash
rylees generate --start <ref> --end <ref> [options]
```

### Options

| Short | Long      | Value       | Description                                      | Default |
| :---- | :-------- | :---------- | :----------------------------------------------- | :------ |
| -s    | --start   | <tag\|hash> | Start tag or commit hash                         | —       |
| -e    | --end     | <tag\|hash> | End tag or commit hash                           | HEAD    |
| -t    | --type    | tag\|commit | Interpret `--start` / `--end` as tags or commits | tag     |
| —     | --major   | —           | Bump the major version                           | false   |
| —     | --minor   | —           | Bump the minor version                           | true    |
| —     | --patch   | —           | Bump the patch version                           | false   |
| -p    | --publish | —           | **⚠ DANGER!**: Publish the release notes unseen  | —       |

### Examples

Generate notes between two tags and bump the minor version:

```bash
rylees gen --start v1.2.0 --end v1.3.0 --minor
```

Preview notes between two commits without publishing:

```bash
rylees gen -s 8f2a1c4 -e HEAD -t commit
```

## Help

Display this help text.

```bash
rylees help
```
