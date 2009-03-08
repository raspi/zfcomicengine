CREATE OR REPLACE VIEW VIEW_RATES
AS
SELECT
  id,
  comicid,
  rate
FROM
  COMMENTS
WHERE
  rate IS NOT NULL AND
  isstaff=0
;

CREATE OR REPLACE VIEW VIEW_AVG_RATES
AS
SELECT
  comicid,
  COUNT(id) AS ratescount,
  AVG(rate) AS avgrate
FROM
  VIEW_RATES
GROUP BY
  comicid
ORDER BY
  comicid
;

CREATE OR REPLACE VIEW VIEW_POSTS
AS
SELECT
  p.id,
  a.name,
  p.subject,
  p.content,
  UNIX_TIMESTAMP(p.added) AS uadded
FROM
  POSTS AS p,
  AUTHORS AS a
WHERE
  a.id=p.authorid
GROUP BY
  p.added,p.id
ORDER BY
  p.added DESC,p.id
;

CREATE OR REPLACE VIEW VIEW_COMICS
AS
SELECT
  c.id,
  a.name AS author,
  a.id AS aid,
  c.name,
  c.filemime,
  c.filesize,
  c.md5sum,
  c.idea,
  UNIX_TIMESTAMP(c.published) AS upublished,
  c.filename,
  c.imgwidth,
  c.imgheight,
  r.avgrate,
  r.ratescount
FROM
(
  (COMICS AS c),
  (AUTHORS AS a)
)
LEFT JOIN
  VIEW_AVG_RATES AS r ON r.comicid=c.id
WHERE
  c.authorid=a.id
;

CREATE OR REPLACE VIEW VIEW_COMMENTS
AS
SELECT
  c.id,
  c.nick,
  c.comment,
  c.country,
  c.rate,
  UNIX_TIMESTAMP(c.added) AS uadded,
  a.name AS author,
  o.id AS comicid,
  o.name AS title
FROM
  COMMENTS AS c,
  COMICS AS o,
  AUTHORS AS a
WHERE
  c.comicid=o.id AND
  o.authorid=a.id
ORDER BY
  c.added DESC
;

