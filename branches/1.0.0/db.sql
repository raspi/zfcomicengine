-- phpMyAdmin SQL Dump
-- version 2.11.8.1deb1
-- http://www.phpmyadmin.net
--
-- Palvelin: localhost
-- Luontiaika: 26.02.2009 klo 18:47
-- Palvelimen versio: 5.0.67
-- PHP:n versio: 5.2.6-2ubuntu4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Tietokanta: `zfce`
--

-- --------------------------------------------------------

--
-- Rakenne taululle `AUTHORS`
--

CREATE TABLE IF NOT EXISTS `AUTHORS` (
  `id` int(11) NOT NULL auto_increment,
  `name` text collate utf8_swedish_ci,
  `email` varchar(255) collate utf8_swedish_ci NOT NULL,
  `password` varchar(32) collate utf8_swedish_ci NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci CHECKSUM=1;

-- --------------------------------------------------------

--
-- Rakenne taululle `COMICS`
--

CREATE TABLE IF NOT EXISTS `COMICS` (
  `id` int(11) NOT NULL auto_increment,
  `name` text collate utf8_swedish_ci NOT NULL,
  `filemime` text collate utf8_swedish_ci NOT NULL,
  `filedata` longblob NOT NULL,
  `filesize` int(11) NOT NULL,
  `md5sum` varchar(32) collate utf8_swedish_ci NOT NULL,
  `authorid` int(11) NOT NULL,
  `idea` varchar(255) collate utf8_swedish_ci default NULL,
  `published` datetime NOT NULL,
  `added` datetime NOT NULL,
  `filename` varchar(255) collate utf8_swedish_ci NOT NULL,
  `imgwidth` int(11) unsigned NOT NULL default '0',
  `imgheight` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `fk_COMICS_AUTHORS` (`authorid`),
  KEY `fk_COMICS_AUTHORS1` (`idea`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci CHECKSUM=1;

-- --------------------------------------------------------

--
-- Rakenne taululle `COMMENTS`
--

CREATE TABLE IF NOT EXISTS `COMMENTS` (
  `id` int(11) NOT NULL auto_increment,
  `nick` varchar(255) collate utf8_swedish_ci NOT NULL,
  `comment` varchar(255) collate utf8_swedish_ci NOT NULL,
  `comicid` int(11) NOT NULL,
  `added` datetime NOT NULL,
  `isstaff` int(11) NOT NULL default '0',
  `rate` int(11) default NULL,
  `country` varchar(32) collate utf8_swedish_ci NOT NULL default 'unknown',
  `ipaddr` varchar(255) collate utf8_swedish_ci NOT NULL,
  `useragent` text collate utf8_swedish_ci NOT NULL,
  `isspam` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `fk_COMMENTS_COMICS` (`comicid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci CHECKSUM=1;

-- --------------------------------------------------------

--
-- Rakenne taululle `GUESTBOOK`
--

CREATE TABLE IF NOT EXISTS `GUESTBOOK` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) collate utf8_swedish_ci default NULL,
  `email` varchar(255) collate utf8_swedish_ci default NULL,
  `question` text collate utf8_swedish_ci NOT NULL,
  `answer` text collate utf8_swedish_ci,
  `added` datetime NOT NULL,
  `country` varchar(32) collate utf8_swedish_ci NOT NULL,
  `ipaddr` varchar(255) collate utf8_swedish_ci NOT NULL,
  `useragent` text collate utf8_swedish_ci NOT NULL,
  `isspam` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci CHECKSUM=1;

-- --------------------------------------------------------

--
-- Rakenne taululle `PAGES`
--

CREATE TABLE IF NOT EXISTS `PAGES` (
  `name` varchar(255) collate utf8_swedish_ci NOT NULL,
  `content` longtext collate utf8_swedish_ci NOT NULL,
  `updated` datetime NOT NULL,
  `added` datetime NOT NULL,
  PRIMARY KEY  (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci CHECKSUM=1;

-- --------------------------------------------------------

--
-- Rakenne taululle `POSTS`
--

CREATE TABLE IF NOT EXISTS `POSTS` (
  `id` int(11) NOT NULL auto_increment,
  `subject` text collate utf8_swedish_ci NOT NULL,
  `content` longtext collate utf8_swedish_ci NOT NULL,
  `authorid` int(11) NOT NULL,
  `added` datetime NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `fk_POSTS_AUTHORS` (`authorid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci CHECKSUM=1;

--
-- Rajoitteet vedostauluille
--

--
-- Rajoitteet taululle `COMICS`
--
ALTER TABLE `COMICS`
  ADD CONSTRAINT `COMICS_ibfk_1` FOREIGN KEY (`authorid`) REFERENCES `AUTHORS` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Rajoitteet taululle `COMMENTS`
--
ALTER TABLE `COMMENTS`
  ADD CONSTRAINT `COMMENTS_ibfk_1` FOREIGN KEY (`comicid`) REFERENCES `COMICS` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Rajoitteet taululle `POSTS`
--
ALTER TABLE `POSTS`
  ADD CONSTRAINT `POSTS_ibfk_1` FOREIGN KEY (`authorid`) REFERENCES `AUTHORS` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
