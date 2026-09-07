# Changelog

## 1.0.5

- Clone-progress lookups are now scope-free; fixes infinite recursion when
  consumer models carry Eloquent global scopes.

  `Cloner::fetchExistingClone()` and `Cloner::isClone()` answer "have I already
  cloned this row in this run?" — bookkeeping that must see every row regardless
  of any consumer's global scopes. They previously resolved the clone through
  the `ModelCloneProgress::clone` morphTo (and a scoped relation), so a freshly
  created clone that its own global scope filtered out (e.g. a team resolved
  through a not-yet-cloned parent) looked "not cloned yet" and was cloned again —
  forever, on a cyclic relation graph. Both lookups now query the clone row by
  class and key without global scopes. Relation-walking that decides *which*
  related rows to clone keeps its scopes.

- The clone-progress cache fast-path now reads with the same `eb-cloner` cache
  tag it is written with (previously the read was untagged, so it never hit).
