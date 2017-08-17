<?php

function audit_get_word_count($dom){
  $output = false;

  $body_elements = $dom->getElementsByTagName('body');

  $body = $body_elements->item(0);

  $html = preg_replace("/(<br[\s]*?\/?>|(&nbsp;|[\b\s\t\r\n ]))+/usi", " ", $dom->saveHTML($body));

  $value = trim(preg_replace("/([\r\n\t]+|([\b\s\t\r\n ]+[\b\s\t\r\n ]+)+|(&nbsp;+&nbsp;+|[\b\s\t\r\n ]+&nbsp;+|&nbsp;+[\b\r\n\t\s ]+))/usi", " ", trim(strip_tags($html))));

  $output = str_word_count($value);

  return array_filter($output);
}

function audit_get_img_tags($dom){
  $output = false;

  foreach($dom->getElementsByTagName('img') as $image){
    $src = $image->getAttribute('src');

    $output[$src] = array(
      'src'    => $src,
      'width'  => $image->getAttribute('width'),
      'height' => $image->getAttribute('height'),
      'alt'    => $image->getAttribute('alt'),
      'title'  => $image->getAttribute('title')
    );
  }

  return array_filter($output);
}

function audit_get_meta_tags($dom){
  $output = false;

  $meta_tags = $dom->getElementsByTagName('meta');

  foreach($meta_tags as $meta_tag){
    $name  = $meta_tag->getAttribute('name');
    $group = 'meta_tags';

    if(!$name){
      $name  = $meta_tag->getAttribute('property');
      $group = 'meta_properties';

      if(!$name){
        $name  = $meta_tag->getAttribute('http-equiv');
        $group = 'meta_headers';
      }
    }

    $value = $meta_tag->getAttribute('content');

    $output[$group][$name] = array(
      'name'  => $name,
      'value' => $value,
      'html'  => $dom->saveHTML($meta_tag)
    );
  }

  return $output;
}

function audit_get_title_tags($dom){
  $output = false;

  $titles = $dom->getElementsByTagName('title');

  foreach($titles as $title){
    $nodeValue = trim(strip_tags($title->nodeValue));

    $output[] = $nodeValue;
  }

  return $output;
}

function audit_get_data($page){
  return array();
}

function audit_get_pages($page){
  $output = false;

  $links = audit_get_internal_links($page);

  foreach(&$links as $url => $link){
    if(!(isset($output[$url]) && $url == $page['url'])){
      $output[$url] = audit_get_page($url);

      $output += audit_get_pages($output[$url]);
    }
  }

  return $output;
}

function audit_get_internal_links($page){
  $links = audit_get_anchor_tags($page);

  return $links['internals'];
}

function audit_get_anchor_tags($page){
  $output = false;

  extract($page);

  $url_pattern = str_replace(array("/", ".", "https:", "http:"), array("(\/)?", "\.", "(https?:)?", "(https?:)?"), $url);

  foreach($dom->getElementsByTagName('a') as $link){
    $anchor = trim(strip_tags($link->nodeValue));
    $title  = $link->getAttribute('title');
    $href   = $link->getAttribute('href');

    if(preg_match("/^mailto:(.*)?/si", $href, $match)){
      $emails[$href] = array(
        'email'  => $match[1],
        'href'   => $href,
        'anchor' => $anchor,
        'title'  => $title,
      );
    }

    if(preg_match("/^tel:(.*)?/si", $href, $match)){
      $phones[$href] = array(
        'phone'  => trim($match[1]),
        'href'   => $href,
        'anchor' => $anchor,
        'title'  => $title,
      );
    }

    if(preg_match("/^(\/|\/?#|$url_pattern)/si", $href, $match) && !preg_match("/^(\/\/)/si", $href, $match)){
      if(preg_match("/^(($url_pattern)?\/?#)/si", $href, $match)){
        $anchors[$href] = array(
          'href'   => $href,
          'anchor' => $anchor,
          'title'  => $title,
        );
      } else {
        $in_href = $href;

        if(!preg_match("/^($url_pattern)/si", $href, $match)){
          $in_href = str_replace('//', '/', "$url/$href");
        }

        if(preg_match('/\.[^\.]+$/i', $href, $match) && !preg_match("/($url_pattern)$/i", $href, $match)){
          $files[$in_href] = array(
            'href'   => $in_href,
            'anchor' => $anchor,
            'title'  => $title,
          );
        } else {
          $internals[$in_href] = array(
            'href'   => $in_href,
            'anchor' => $anchor,
            'title'  => $title,
          );
        }
      }
    } elseif(preg_match("/^(\/\/|https?:\/\/)/si", $href, $match) && !preg_match("/^($url_pattern)/si", $href, $match)){
      $externals[$href] = array(
        'href'   => $href,
        'anchor' => $anchor,
        'title'  => $title,
      );
    }

    $output['all'][$href] = array(
      'href'   => $link->getAttribute('href'),
      'anchor' => $anchor,
      'title'  => $title,
    );
  }

  $output += array(
    'emails'    => $emails,
    'phones'    => $phones,
    'anchors'   => $anchors,
    'files'     => $files,
    'internals' => $internals,
    'externals' => $externals
  );

  return array_filter($output);
}

function audit_get_page($url){
  $output = false;

  $page = array(
    'url' => $url,
    'dom' => audit_get_document($url)
  );

  return $output;
}

function audit_get_document($url){
  $output = false;

  libxml_use_internal_errors(true);

  $dom = new DomDocument();

  $output = $dom->loadHTML(audit_get_content($url));

  return $output;
}

function audit_get_content($url, $maximumRedirections = 5, $currentRedirection = 0){
  $output = false;

  $content = @file_get_contents($url);

  if(isset($content) && is_string($content)){
    preg_match_all('/<[\s]*meta[\s]*http-equiv="?REFRESH"?' . '[\s]*content="?[0-9]*;[\s]*URL[\s]*=[\s]*([^>"]*)"?' . '[\s]*[\/]?[\s]*>/si', $content, $match);

    if(isset($match) && is_array($match) && count($match) == 2 && count($match[1]) == 1){
      if(!isset($maximumRedirections) || $currentRedirection < $maximumRedirections){
        $output = audit_get_content($match[1][0], $maximumRedirections, ++$currentRedirection);
      }
    } else {
      $output = $content;
    }
  }

  return $output;
}
