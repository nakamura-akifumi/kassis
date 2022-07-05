package main

import (
	"flag"
	"fmt"
	kassiscore "github.com/nakamura-akifumi/kassis"
)

func main() {

	fmt.Printf("kassis configrator. version %s (Revison:%s)\n", kassiscore.VERSION, kassiscore.REVISION)
	fmt.Println("Main function started")

	generateDefaultConfigSet := flag.Bool("generate-default-configset", false, "generate default configset to current directory")
	setupSolr := flag.Bool("setup-solr", false, "setup solr schema")
	deleteAlldata := flag.Bool("delete-alldata", false, "delete all solr data")
	startSolr := flag.Bool("start-solr", false, "Start solr")
	downloadapp := flag.Bool("download-app", false, "download apps")
	flag.Parse()

	fmt.Println("generateDefaultConfigSet:", *generateDefaultConfigSet)
	fmt.Println("setupSolr:", *setupSolr)
	fmt.Println("deleteAlldata:", *deleteAlldata)
	fmt.Println("downloadapp:", *downloadapp)

	if *generateDefaultConfigSet == true {
		kassiscore.GenerateDefaultConfigSet()
	} else if *setupSolr == true {
		kassiscore.SetupSolr()
	} else if *deleteAlldata == true {
		cfg := kassiscore.LoadConfig()
		kassiscore.SolrClearDocument(cfg.Solr.Serveruri, cfg.Solr.Corename)
	} else if *startSolr == true {
		cfg := kassiscore.LoadConfig()
		kassiscore.StartSolr(cfg)
	} else if *downloadapp == true {
		kassiscore.DownloadApps()
	} else {
		kassiscore.CheckConfigAndConnections()
	}

}
