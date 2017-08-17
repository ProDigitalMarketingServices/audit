<?php
header("Access-Control-Allow-Origin:*");
header("Content-type:application/json");

$json_flags = JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT | JSON_PRETTY_PRINT;

if(in_array('url', $_REQUEST)){
  print(json_encode(audit_get_data(audit_get_document($_REQUEST['url'])), $json_flags));
}

function audit_get_data($dom){
  return array(
    'html'            => audit_get_html($dom),
    'titles'          => audit_get_titles($dom),
    'metas'           => audit_get_metas($dom),
    'structured_data' => audit_get_structured_data($dom),
    'links'           => audit_get_links($dom),
    'imgs'            => audit_get_imgs($dom),
    'word_count'      => audit_get_word_count($dom)
  );
}

function audit_get_word_count($dom){
  $body_elements = $dom->getElementsByTagName('body');

  $body = $body_elements->item(0);

  $html = preg_replace("/(<br[\s]*?\/?>|(&nbsp;|[\b\s\t\r\n ]))+/usi", " ", $dom->saveHTML($body));

  $value = trim(preg_replace("/([\r\n\t]+|([\b\s\t\r\n ]+[\b\s\t\r\n ]+)+|(&nbsp;+&nbsp;+|[\b\s\t\r\n ]+&nbsp;+|&nbsp;+[\b\r\n\t\s ]+))/usi", " ", trim(strip_tags($html))));

  return str_word_count($value);
}

function audit_get_imgs($dom){
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

function audit_get_links($dom){
  foreach($dom->getElementsByTagName('a') as $link){
    $output[] = array(
      'url' => $link->getAttribute('href'),
      'title' => $link->getAttribute('title'),
      'anchor_text' => trim(strip_tags($link->nodeValue)),
      'html' => $dom->saveHTML($link),
    );
  }

  return $output;
}

function audit_get_structured_data($dom){ // Only supports ld+json atm
  foreach($dom->getElementByTagName('script') as $script){
    if($script->getAttribute('type') == 'application/ld+json'){
      $output[] = json_decode($script->nodeValue);
    }
  }

  return $output;
}

function audit_get_metas($dom){
  foreach($dom->getElementsByTagName('meta') as $meta_tag){
    $name  = $meta_tag->getAttribute('name');
    $group = 'attributes';

    if(!$name){
      $name  = $meta_tag->getAttribute('property');
      $group = 'properties';

      if(!$name){
        $name  = $meta_tag->getAttribute('http-equiv');
        $group = 'headers';
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

function audit_get_titles($dom){
  foreach($dom->getElementsByTagName('title') as $title){
    $output[] = trim(strip_tags($title->nodeValue));
  }

  return $output;
}

function audit_get_html($dom){
  $html = $dom->getElementsByTagName('body')->item(0);

  return $dom->saveHTML($html);
}

function audit_get_document($url){
  libxml_use_internal_errors(true);

  $dom = new DomDocument();

  $output = $dom->loadHTML(audit_get_content($url));

  return $output;
}

function audit_get_content($url, $maximumRedirections = 5, $currentRedirection = 0){
  $output = false;

  if(audit_get_content_type($url) == 'text/html'){
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
  }

  return $output;
}

function audit_get_content_type($url){
  $headers = get_headers($url, 1);

  return $headers['Content-type'];
}
