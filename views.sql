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
